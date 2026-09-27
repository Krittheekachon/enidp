<?php

namespace App\Http\Controllers;

use App\Models\CompetencyType;
use App\Models\User;
use App\Services\AssessmentRoundReadinessService;
use App\Services\ExpectedLevelResolver;
use App\Services\FacultyAnalyticsService;
use App\Services\ReviewerChainResolver;
use App\Services\ReviewerTemplateResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    private const SELF_ASSESSMENT_ROLE_KEYS = [
        'employee',
        'supervisor',
        'dept_head',
        'division_head',
        'academic_department_head',
        'manager_dept',
        'hr',
    ];

    private ?array $competencyLevelsByCompetency = null;

    private array $worklineIdCache = [];

    private array $jobFamilyNameCache = [];

    private array $supportUnitStructureCache = [];

    private array $assessmentReviewerStepsCache = [];

    public function __construct(
        private ReviewerChainResolver $reviewerChainResolver,
        private ReviewerTemplateResolver $reviewerTemplateResolver,
        private FacultyAnalyticsService $facultyAnalyticsService,
        private AssessmentRoundReadinessService $assessmentRoundReadiness,
    ) {}

    /**
     * จัดการหน้า Dashboard ตาม Role ID
     */
    public function index(Request $request)
    {
        $currentUser = auth()->user()->loadMissing('role');
        $role = $this->roleKeyForUser($currentUser);
        $competencyTypes = $role === 'admin'
            ? CompetencyType::orderBy('code')->get()->map(fn (CompetencyType $type) => [
                'id' => $type->id,
                'code' => $type->code,
                'fullName' => $type->full_name,
                'desc' => $type->description,
            ])
            : collect();
        $competencies = in_array($role, ['admin', 'hr'], true)
            ? $this->competencyPayload()
            : collect();
        $users = $this->dashboardUsersForRole($currentUser, $role);
        $facultyAnalytics = in_array($role, ['hr', 'dean'], true)
            ? $this->facultyAnalyticsService->build($request->integer('assessment_round_id') ?: null)
            : null;
        $activeCycleName = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('name') ?? 'รอบประเมินปัจจุบัน';
        $learningMethods = $this->canonicalLearningMethods();
        $managerSummary = [
            'totalUsers' => User::count(),
            'evaluatedUsers' => 0,
            'passedUsers' => 0,
            'failedUsers' => 0,
            'trainingNeeds' => 0,
            'pendingAssessmentApprovals' => 0,
            'pendingIdpApprovals' => 0,
            'source' => 'database',
        ];
        $activeRoundId = $this->activeRoundId();
        $approvedIdpActivities = $this->currentUserApprovedIdpActivities($currentUser, $activeRoundId);
        $idpReviewItems = $this->idpReviewItemsForReviewer($currentUser, $activeRoundId);
        $idpProgressReviewItems = $this->idpActivityProgressReviewItems($currentUser, null, $activeRoundId);
        $idpReviewModule = $this->idpReviewModuleForReviewer($currentUser);
        $teamIdpAnalytics = null;
        if ($idpReviewModule['enabled'] ?? false) {
            $teamIdpRoundId = $request->integer('assessment_round_id') ?: $activeRoundId;
            $teamUserIds = collect([
                ...$this->reviewerChainResolver->userIdsForReviewer($currentUser, 'assessment'),
                ...$this->reviewerChainResolver->userIdsForReviewer($currentUser, 'idp'),
            ])->map(fn ($id): int => (int) $id)->unique()->values()->all();
            $teamIdpDetails = $this->idpActivityProgressReviewItems(
                $currentUser,
                $teamUserIds,
                $teamIdpRoundId,
            );
            $teamIdpAnalytics = $this->facultyAnalyticsService->build($teamIdpRoundId, $teamUserIds);
            $teamIdpAnalytics['idpDetails'] = $teamIdpDetails;
        }
        if ($facultyAnalytics && isset($facultyAnalytics['round']['id'])) {
            $facultyUserIds = collect($facultyAnalytics['heatmap'] ?? [])
                ->flatMap(fn (array $row): array => $row['faculty']['people'] ?? [])
                ->pluck('userId')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $facultyAnalytics['idpDetails'] = $this->idpActivityProgressReviewItems(
                $currentUser,
                $facultyUserIds,
                (int) $facultyAnalytics['round']['id'],
            );
        }
        $fcTopicApprovalModule = $this->fcTopicApprovalModuleForReviewer($currentUser);
        $assessmentApprovalModule = $this->assessmentApprovalModuleForReviewer($currentUser);
        $selfServicePayload = [
            'activeCycleName' => $activeCycleName,
            'currentUser' => $this->dashboardUserPayload($currentUser),
            'currentUserCompetencies' => $this->assignedCompetenciesForUser($currentUser),
            'currentUserFcTopicSelection' => $this->fcTopicSelectionPayloadForUser($currentUser),
            'currentUserCompetencyGaps' => $this->competencyGapsForUser($currentUser),
            'currentUserIdp' => $this->currentUserIdpPayload($currentUser),
            'learningMethods' => $learningMethods,
            'hrCatalogItems' => $this->learningCatalogItems(),
            'idpLearningMethods' => $this->idpLearningMethods(),
            'idpReviewItems' => $idpReviewItems,
            'idpReviewModule' => $idpReviewModule,
            'idpProgressReviewItems' => $idpProgressReviewItems,
            'teamIdpAnalytics' => $teamIdpAnalytics,
            'fcTopicApprovalModule' => $fcTopicApprovalModule,
            'assessmentApprovalModule' => $assessmentApprovalModule,
        ];

        return match ($role) {
            'admin' => Inertia::render('Admin/Dashboard', [
                ...$selfServicePayload,
                'users' => $users,
                'roles' => $this->rolesPayload(),
                'reviewerChainTemplates' => $this->reviewerTemplateResolver->payload(),
                'competencyTypes' => $competencyTypes,
                'competencies' => $competencies,
                ...$this->adminStructurePayload(),
                'learningMethods' => $learningMethods,
                'hrCatalogItems' => $this->learningCatalogItems(),
                'idpLearningMethods' => $this->idpLearningMethods(),
                'idpDeliveryTypeSettings' => $this->idpDeliveryTypeSettings(),
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'supervisor' => Inertia::render('Super/Dashboard', [
                ...$selfServicePayload,
                'users' => $users,
                'roleKey' => 'supervisor',
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'fcTopicApprovalModule' => $fcTopicApprovalModule,
                'assessmentApprovalModule' => $assessmentApprovalModule,
                'currentUserCompetencies' => $this->assignedCompetenciesForUser($currentUser),
                'currentUserFcTopicSelection' => $this->fcTopicSelectionPayloadForUser($currentUser),
                'currentUserCompetencyGaps' => $this->competencyGapsForUser($currentUser),
                'idpReviewItems' => $idpReviewItems,
                'idpProgressReviewItems' => $idpProgressReviewItems,
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'dept_head' => Inertia::render('Super/Dashboard', [
                ...$selfServicePayload,
                'users' => $users,
                'roleKey' => 'dept_head',
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'fcTopicApprovalModule' => $fcTopicApprovalModule,
                'assessmentApprovalModule' => $assessmentApprovalModule,
                'currentUserCompetencies' => $this->assignedCompetenciesForUser($currentUser),
                'currentUserFcTopicSelection' => $this->fcTopicSelectionPayloadForUser($currentUser),
                'currentUserCompetencyGaps' => $this->competencyGapsForUser($currentUser),
                'idpReviewItems' => $idpReviewItems,
                'idpProgressReviewItems' => $idpProgressReviewItems,
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'division_head' => Inertia::render('Super/Dashboard', [
                ...$selfServicePayload,
                'users' => $users,
                'roleKey' => 'division_head',
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'fcTopicApprovalModule' => $fcTopicApprovalModule,
                'assessmentApprovalModule' => $assessmentApprovalModule,
                'currentUserCompetencies' => $this->assignedCompetenciesForUser($currentUser),
                'currentUserFcTopicSelection' => $this->fcTopicSelectionPayloadForUser($currentUser),
                'currentUserCompetencyGaps' => $this->competencyGapsForUser($currentUser),
                'idpReviewItems' => $idpReviewItems,
                'idpProgressReviewItems' => $idpProgressReviewItems,
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'academic_department_head' => Inertia::render('Super/Dashboard', [
                ...$selfServicePayload,
                'users' => $users,
                'roleKey' => 'academic_department_head',
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'fcTopicApprovalModule' => $fcTopicApprovalModule,
                'assessmentApprovalModule' => $assessmentApprovalModule,
                'currentUserCompetencies' => $this->assignedCompetenciesForUser($currentUser),
                'currentUserFcTopicSelection' => $this->fcTopicSelectionPayloadForUser($currentUser),
                'currentUserCompetencyGaps' => $this->competencyGapsForUser($currentUser),
                'idpReviewItems' => $idpReviewItems,
                'idpProgressReviewItems' => $idpProgressReviewItems,
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'employee' => Inertia::render('Employee/Dashboard', [
                ...$selfServicePayload,
                'roleKey' => 'employee',
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'fcTopicApprovalModule' => $fcTopicApprovalModule,
                'assessmentApprovalModule' => $assessmentApprovalModule,
                'currentUserCompetencies' => $this->assignedCompetenciesForUser($currentUser),
                'currentUserFcTopicSelection' => $this->fcTopicSelectionPayloadForUser($currentUser),
                'currentUserCompetencyGaps' => $this->competencyGapsForUser($currentUser),
                'currentUserIdp' => $this->currentUserIdpPayload($currentUser),
                'activeCycleName' => $activeCycleName,
                'learningMethods' => $learningMethods,
                'hrCatalogItems' => $this->learningCatalogItems(),
                'idpLearningMethods' => $this->idpLearningMethods(),
                'idpReviewItems' => $idpReviewItems,
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'hr' => Inertia::render('HR/Dashboard', [
                ...$selfServicePayload,
                'facultyAnalytics' => $facultyAnalytics,
                'hrSummary' => [
                    'totalUsers' => User::count(),
                    'hrUsers' => User::where('role_id', $this->roleIdByKey('hr'))->count(),
                    'employeeUsers' => User::where('role_id', $this->roleIdByKey('employee'))->count(),
                    'source' => 'database',
                ],
                ...$this->hrStructurePayload(),
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'currentUserCompetencies' => $this->assignedCompetenciesForUser($currentUser),
                'currentUserFcTopicSelection' => $this->fcTopicSelectionPayloadForUser($currentUser),
                'currentUserCompetencyGaps' => $this->competencyGapsForUser($currentUser),
                'competencies' => $competencies,
                'learningMethods' => $learningMethods,
                'activeCycleName' => $activeCycleName,
                'assessmentRounds' => $this->assessmentRoundsPayload(),
                'hrCatalogItems' => $this->learningCatalogItems(),
                'currentUserApprovedIdpActivities' => $approvedIdpActivities,
            ]),
            'dean' => Inertia::render('Executive/Dashboard', [
                'facultyAnalytics' => $facultyAnalytics,
                'currentUser' => $this->dashboardUserPayload($currentUser),
                'fcTopicApprovalModule' => $fcTopicApprovalModule,
                'assessmentApprovalModule' => $assessmentApprovalModule,
                'idpReviewModule' => $idpReviewModule,
                'idpReviewItems' => $idpReviewItems,
                'managerSummary' => $managerSummary,
                'activeCycleName' => $activeCycleName,
                'departmentRows' => [],
                'problemCompetencyRows' => [],
                'idpProgressRows' => [],
                'idpNoProgressRows' => [],
                'trainingNeedRows' => [],
                'assessmentApprovals' => [],
                'idpApprovals' => [],
                'idpProgressReviewItems' => $idpProgressReviewItems,
            ]),
            default => Inertia::render('Dashboard'),
        };
    }

    private function canonicalLearningMethods(): array
    {
        return [
            [
                'key' => 'experiential-learning',
                'label' => 'Experiential Learning',
                'desc' => 'การเรียนรู้ผ่านประสบการณ์จากการทำงานจริง เช่น OJT โครงการพิเศษ หรือ Job Rotation',
            ],
            [
                'key' => 'social-learning',
                'label' => 'Social Learning',
                'desc' => 'การเรียนรู้ผ่านบุคคลอื่น การปฏิสัมพันธ์ แลกเปลี่ยนความคิดเห็น ประสบการณ์ร่วมกัน หรือการมีผู้คอยให้คำแนะนำ',
            ],
            [
                'key' => 'formal-learning',
                'label' => 'Formal Learning',
                'desc' => 'การเรียนรู้อย่างเป็นทางการ มีแบบแผน หรือการเรียนในห้องเรียน',
            ],
        ];
    }

    private function assessmentRoundsPayload(): array
    {
        $eligibleUserCount = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.is_active', true)
            ->whereIn('roles.key', self::SELF_ASSESSMENT_ROLE_KEYS)
            ->count('users.id');

        $submittedUsersByRound = DB::table('assessments')
            ->join('users', 'assessments.user_id', '=', 'users.id')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.is_active', true)
            ->whereIn('roles.key', self::SELF_ASSESSMENT_ROLE_KEYS)
            ->whereNotNull('assessments.self_submitted_at')
            ->select([
                'assessments.assessment_round_id',
                DB::raw('COUNT(DISTINCT assessments.user_id) as submitted_user_count'),
            ])
            ->groupBy('assessments.assessment_round_id');

        return DB::table('assessment_rounds')
            ->leftJoinSub($submittedUsersByRound, 'submitted_users', function ($join): void {
                $join->on('assessment_rounds.id', '=', 'submitted_users.assessment_round_id');
            })
            ->select([
                'assessment_rounds.id',
                'assessment_rounds.name',
                'assessment_rounds.year',
                'assessment_rounds.self_assess_start',
                'assessment_rounds.self_assess_end',
                'assessment_rounds.supervisor_assess_end',
                'assessment_rounds.is_active',
                DB::raw('COALESCE(submitted_users.submitted_user_count, 0) as submitted_user_count'),
            ])
            ->orderByDesc('assessment_rounds.is_active')
            ->orderByDesc('assessment_rounds.year')
            ->orderByDesc('assessment_rounds.id')
            ->get()
            ->map(fn (object $round): array => [
                'id' => (int) $round->id,
                'name' => $round->name,
                'year' => (int) $round->year,
                'selfAssessStart' => $round->self_assess_start,
                'selfAssessEnd' => $round->self_assess_end,
                'supervisorAssessEnd' => $round->supervisor_assess_end,
                'isActive' => (bool) $round->is_active,
                'submittedUserCount' => (int) $round->submitted_user_count,
                'eligibleUserCount' => (int) $eligibleUserCount,
                'readiness' => $this->assessmentRoundReadiness->check((int) $round->id),
            ])
            ->all();
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }

    private function dashboardUserPayload(User $user): array
    {
        $department = $this->currentDepartmentForUser($user);
        $approvalOrganization = $this->approvalOrganizationForUser($user, $department);
        $roleKey = $this->roleKeyForUser($user);
        $assignedCompetencies = $this->assignedCompetenciesForUser($user);
        $competencyGaps = $this->competencyGapsForUser($user);
        $evalStatus = $this->evaluationStatusFromGaps($competencyGaps);
        $reviewerSteps = $this->assessmentReviewerStepsCache[$user->id]
            ??= $this->reviewerChainResolver->payloadForUser($user);
        $idpReviewerSteps = $this->reviewerChainResolver->payloadForUser($user, 'idp');
        $structureIssues = $roleKey === 'admin'
            ? []
            : [
                ...$this->structureIssuesForUser($user, $department),
                ...$this->reportingLineIssuesForUser($user),
            ];

        return [
            'db_id' => $user->id,
            'sso' => $user->sso ?: (string) $user->id,
            't' => $user->title ?: '',
            'n' => $user->name,
            'fn' => $user->first_name_th ?: $user->name,
            'ln' => $user->last_name_th ?: '',
            'fe' => $user->first_name_en ?: '',
            'le' => $user->last_name_en ?: '',
            'em' => $user->email,
            'username' => $user->username ?: '',
            'ph' => $user->phone ?: '',
            'w' => $user->workline ?: '',
            'd' => $department,
            'approvalOrg' => $approvalOrganization,
            'p' => $user->position ?: '',
            'l' => $user->level ?: '',
            'position_id' => $user->position_id,
            'level_id' => $user->level_id,
            'r' => $roleKey,
            'reviewer_template_id' => $user->reviewer_template_id,
            'idp_reviewer_template_id' => $user->idp_reviewer_template_id ?? null,
            'displayOrganization' => $approvalOrganization,
            'sup' => $reviewerSteps[0]['name'] ?? '',
            'evaluator2' => $reviewerSteps[1]['name'] ?? '',
            'evaluator3' => $reviewerSteps[2]['name'] ?? '',
            'reviewerSteps' => $reviewerSteps,
            'idpReviewerSteps' => $idpReviewerSteps,
            'supervisorChain' => $reviewerSteps,
            'assignedCompetencies' => $assignedCompetencies,
            'competencyGaps' => $competencyGaps,
            'fcTopicSelection' => $this->fcTopicSelectionPayloadForUser($user),
            'gaps' => collect($competencyGaps)
                ->filter(fn (array $gap): bool => (float) ($gap['gap'] ?? 0) < 0)
                ->pluck('n')
                ->values()
                ->all(),
            'evalStatus' => $evalStatus,
            'act' => (bool) $user->is_active,
            'structureStatus' => $structureIssues === [] ? 'ok' : 'invalid',
            'structureIssues' => $structureIssues,
        ];
    }

    private function dashboardUsersForRole(User $currentUser, string $role): Collection
    {
        if ($role === 'admin') {
            return User::with('role')
                ->orderByDesc('id')
                ->get()
                ->map(fn (User $user): array => $this->dashboardUserPayload($user));
        }

        if (! in_array($role, ['supervisor', 'dept_head', 'division_head', 'academic_department_head'], true)) {
            return collect();
        }

        $visibleUserIds = collect([
            $currentUser->id,
            ...$this->reviewerChainResolver->userIdsForReviewer($currentUser, 'assessment'),
            ...$this->reviewerChainResolver->userIdsForReviewer($currentUser, 'idp'),
        ])->map(fn ($id): int => (int) $id)->unique()->values();

        return User::with('role')
            ->whereIn('id', $visibleUserIds)
            ->where(function ($query) use ($currentUser): void {
                $query->where('is_active', true)->orWhere('id', $currentUser->id);
            })
            ->orderByDesc('id')
            ->get()
            ->map(function (User $user): array {
                $payload = $this->dashboardUserPayload($user);
                unset(
                    $payload['em'],
                    $payload['username'],
                    $payload['ph'],
                    $payload['reviewer_template_id'],
                    $payload['idp_reviewer_template_id'],
                );

                return $payload;
            });
    }

    private function evaluationStatusFromGaps(array $competencyGaps): string
    {
        if ($competencyGaps === []) {
            return 'draft';
        }

        $statuses = collect($competencyGaps)
            ->pluck('status')
            ->filter()
            ->values();

        if ($statuses->contains('revision_required')) {
            return 'revision_required';
        }

        if ($statuses->contains('self_submitted')) {
            return 'self_submitted';
        }

        if ($statuses->contains('unit_evaluated')) {
            return 'unit_evaluated';
        }

        if ($statuses->contains('dept_evaluated')) {
            return 'dept_evaluated';
        }

        $dynamicReviewStatus = $statuses->first(
            fn (string $status): bool => str_starts_with($status, 'review_step_')
        );

        if ($dynamicReviewStatus) {
            return $dynamicReviewStatus;
        }

        if ($statuses->contains('approved') || $statuses->contains('dean_approved')) {
            return 'approved';
        }

        return 'draft';
    }

    private function displayNameForUser(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return trim(($user->title ?: '').$user->name);
    }

    private function supervisorChainForUser(User $user): array
    {
        return $this->reviewerChainResolver->payloadForUser($user);
    }

    private function decodeJsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function isoUtcTimestamp(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return Carbon::parse((string) $value, 'UTC')->utc()->toISOString();
    }

    private function currentDepartmentForUser(User $user): string
    {
        if ($this->usesSupportUnitStructure($user)) {
            $supportDepartment = DB::table('positions')
                ->join('support_units', 'positions.support_unit_id', '=', 'support_units.id')
                ->join('support_works', 'support_units.support_work_id', '=', 'support_works.id')
                ->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
                ->where('positions.id', $user->position_id)
                ->select('support_departments.name as department_name', 'support_works.name as work_name', 'support_units.name as unit_name')
                ->first();

            if ($supportDepartment) {
                return implode(' > ', [
                    $supportDepartment->department_name,
                    $supportDepartment->work_name,
                    $supportDepartment->unit_name,
                ]);
            }
        }

        if ($this->hasStoredSupportHierarchy($user)) {
            return $user->department ?: '';
        }

        $currentJobFamily = $this->currentJobFamilyNameForUser($user);

        if (! $currentJobFamily) {
            return $user->department ?: '';
        }

        $suffix = $this->departmentSuffix($user->department ?: '');

        return $currentJobFamily.$suffix;
    }

    private function approvalOrganizationForUser(User $user, ?string $department = null): string
    {
        $department ??= $this->currentDepartmentForUser($user);
        $parts = array_values(array_filter(array_map('trim', explode(' > ', $department))));
        $isSupport = $this->usesSupportUnitStructure($user)
            || in_array($user->workline, ['สายสนับสนุน', 'สายงานสนับสนุน'], true);

        if ($isSupport) {
            return $parts[array_key_last($parts)] ?? $department;
        }

        return $parts[0] ?? $department;
    }

    private function currentJobFamilyNameForUser(User $user): ?string
    {
        $cacheKey = $user->position_id
            ? 'position:'.$user->position_id
            : 'structure:'.implode('|', [$user->workline, $user->position]);

        if (array_key_exists($cacheKey, $this->jobFamilyNameCache)) {
            return $this->jobFamilyNameCache[$cacheKey];
        }

        if ($user->position_id) {
            return $this->jobFamilyNameCache[$cacheKey] = DB::table('positions')
                ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
                ->where('positions.id', $user->position_id)
                ->value('job_families.name');
        }

        if (! $user->workline || ! $user->position) {
            return $this->jobFamilyNameCache[$cacheKey] = null;
        }

        $matches = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->join('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->where('worklines.name', $user->workline)
            ->where('positions.name', $user->position)
            ->pluck('job_families.name')
            ->unique()
            ->values();

        return $this->jobFamilyNameCache[$cacheKey] = $matches->count() === 1
            ? $matches->first()
            : null;
    }

    private function departmentSuffix(string $department): string
    {
        $parts = explode(' > ', $department, 2);

        return isset($parts[1]) ? ' > '.$parts[1] : '';
    }

    private function structureIssuesForUser(User $user, string $department): array
    {
        $issues = [];
        $roleKey = $this->roleKeyForUser($user);

        if ($roleKey === 'dean') {
            return $issues;
        }

        if (! $user->workline) {
            $issues[] = 'ยังไม่ได้กำหนดสายงาน';

            return $issues;
        }

        $worklineId = DB::table('worklines')->where('name', $user->workline)->value('id');
        if (! $worklineId) {
            $issues[] = 'สายงานนี้ไม่มีในโครงสร้างปัจจุบัน';

            return $issues;
        }

        if ($this->usesSupportUnitStructure($user) || $this->isSupportDepartmentPath($user, $department) || $this->hasStoredSupportHierarchy($user)) {
            return $this->supportStructureIssuesForUser($user, (int) $worklineId, $department);
        }

        $jobFamilyName = $this->jobFamilyNameFromDepartment($department);
        $jobFamilyId = $jobFamilyName
            ? DB::table('job_families')
                ->where('workline_id', $worklineId)
                ->where('name', $jobFamilyName)
                ->value('id')
            : null;

        if (! $jobFamilyId) {
            $issues[] = 'กลุ่มงานนี้ไม่มีในโครงสร้างปัจจุบัน';
        }

        if ($user->position) {
            $positionExists = $jobFamilyId
                ? DB::table('positions')
                    ->where('job_family_id', $jobFamilyId)
                    ->where('name', $user->position)
                    ->exists()
                : false;
            $usesJobFamilyAsPosition = $roleKey === 'dean'
                && $jobFamilyName !== ''
                && $user->position === $jobFamilyName;

            if (! $positionExists && ! $usesJobFamilyAsPosition) {
                $issues[] = 'ตำแหน่งนี้ไม่มีในกลุ่มงานปัจจุบัน';
            }
        } else {
            $issues[] = 'ยังไม่ได้กำหนดตำแหน่ง';
        }

        if ($user->level) {
            $levelExists = DB::table('levels')
                ->where('workline_id', $worklineId)
                ->where('name', $user->level)
                ->whereNull('job_family_id')
                ->exists();

            if (! $levelExists) {
                $issues[] = 'ระดับตำแหน่งนี้ไม่มีในโครงสร้างปัจจุบัน';
            }
        } else {
            $issues[] = 'ยังไม่ได้กำหนดระดับตำแหน่ง';
        }

        return $issues;
    }

    private function supportStructureIssuesForUser(User $user, int $worklineId, string $department): array
    {
        $issues = [];
        $roleKey = $this->roleKeyForUser($user);
        $path = array_values(array_filter(array_map('trim', explode(' > ', $department))));
        $pathCount = count($path);
        $departmentExists = $pathCount >= 1
            ? DB::table('support_departments')->where('name', $path[0])->exists()
            : false;
        $workExists = $pathCount >= 2
            ? DB::table('support_works')
                ->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
                ->where('support_departments.name', $path[0])
                ->where('support_works.name', $path[1])
                ->exists()
            : false;
        $supportUnitId = count($path) === 3
            ? DB::table('support_units')
                ->join('support_works', 'support_units.support_work_id', '=', 'support_works.id')
                ->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
                ->where('support_departments.name', $path[0])
                ->where('support_works.name', $path[1])
                ->where('support_units.name', $path[2])
                ->value('support_units.id')
            : null;
        $hasValidSupportPath = match ($roleKey) {
            'division_head' => $pathCount === 1
                ? $departmentExists
                : ($pathCount === 2 ? $departmentExists && $workExists : (bool) $supportUnitId),
            'dept_head' => $pathCount === 2
                ? $departmentExists && $workExists
                : (bool) $supportUnitId,
            default => (bool) $supportUnitId,
        };

        if (! $hasValidSupportPath) {
            $issues[] = 'ฝ่าย งาน หรือหน่วยนี้ไม่มีในโครงสร้างสายสนับสนุนปัจจุบัน';
        }

        if (! $user->position) {
            $issues[] = 'ยังไม่ได้กำหนดตำแหน่ง';
        } else {
            $globalPositionExists = DB::table('positions')
                ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
                ->where('job_families.workline_id', $worklineId)
                ->whereNull('positions.support_unit_id')
                ->where('positions.name', $user->position)
                ->when($user->position_id, fn ($query) => $query->where('positions.id', $user->position_id))
                ->exists();
            $unitPositionExists = $supportUnitId
                ? DB::table('positions')
                    ->where('support_unit_id', $supportUnitId)
                    ->where('name', $user->position)
                    ->when($user->position_id, fn ($query) => $query->where('id', $user->position_id))
                    ->exists()
                : false;

            if (! $globalPositionExists && ! $unitPositionExists) {
                $issues[] = 'ตำแหน่งนี้ไม่มีในหน่วยงานสายสนับสนุนปัจจุบัน';
            }
        }

        if (! $user->level) {
            $issues[] = 'ยังไม่ได้กำหนดระดับตำแหน่ง';
        } else {
            $levelExists = DB::table('levels')
                ->where('workline_id', $worklineId)
                ->where('name', $user->level)
                ->whereNull('job_family_id')
                ->exists();

            if (! $levelExists) {
                $issues[] = 'ระดับตำแหน่งนี้ไม่มีในโครงสร้างปัจจุบัน';
            }
        }

        return $issues;
    }

    private function usesSupportUnitStructure(User $user): bool
    {
        if (! in_array($user->workline, ['สายสนับสนุน', 'สายงานสนับสนุน'], true) || ! $user->position_id) {
            return false;
        }

        $cacheKey = (int) $user->position_id;
        if (array_key_exists($cacheKey, $this->supportUnitStructureCache)) {
            return $this->supportUnitStructureCache[$cacheKey];
        }

        return $this->supportUnitStructureCache[$cacheKey] = DB::table('positions')
            ->where('id', $user->position_id)
            ->whereNotNull('support_unit_id')
            ->exists();
    }

    private function hasStoredSupportHierarchy(User $user): bool
    {
        if (! in_array($user->workline, ['สายสนับสนุน', 'สายงานสนับสนุน'], true) || blank($user->department)) {
            return false;
        }

        $parts = array_values(array_filter(array_map('trim', explode(' > ', $user->department))));
        if ($parts === []) {
            return false;
        }

        return DB::table('support_departments')->where('name', $parts[0])->exists();
    }

    private function isSupportDepartmentPath(User $user, string $department): bool
    {
        return in_array($user->workline, ['สายสนับสนุน', 'สายงานสนับสนุน'], true)
            && count(array_filter(array_map('trim', explode(' > ', $department)))) === 3;
    }

    private function jobFamilyNameFromDepartment(string $department): string
    {
        return trim(explode(' > ', $department)[0] ?? '');
    }

    private function reportingLineIssuesForUser(User $user): array
    {
        $roleKey = $this->roleKeyForUser($user);

        if (in_array($roleKey, ['admin', 'dean'], true)) {
            return [];
        }

        $issues = [];

        if ($this->reviewerChainResolver->stepsForUser($user, 'assessment') === []) {
            $issues[] = 'ยังไม่ได้กำหนดลำดับการประเมิน';
        }

        if ($this->reviewerChainResolver->stepsForUser($user, 'idp') === []) {
            $issues[] = 'ยังไม่ได้กำหนดลำดับ IDP';
        }

        return $issues;
    }

    private function evaluatorRoleIssuesForUser(User $user): array
    {
        return [];
    }

    private function adminStructurePayload(): array
    {
        $jobFamilies = DB::table('job_families')
            ->leftJoin('worklines', 'job_families.workline_id', '=', 'worklines.id')
            ->select('job_families.id', 'job_families.name', 'worklines.name as workline_name')
            ->orderBy('worklines.name')
            ->orderBy('job_families.name')
            ->get();

        $positionsByFamily = DB::table('positions')
            ->select('id', 'job_family_id', 'support_unit_id', 'name')
            ->orderBy('name')
            ->get()
            ->groupBy('job_family_id');

        $globalPositionsByFamily = DB::table('positions')
            ->whereNull('support_unit_id')
            ->select('id', 'job_family_id', 'name')
            ->orderBy('name')
            ->get()
            ->groupBy('job_family_id');

        $supportDepartments = DB::table('support_departments')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $supportWorksByDepartment = DB::table('support_works')
            ->select('id', 'support_department_id', 'name')
            ->orderBy('name')
            ->get()
            ->groupBy('support_department_id');

        $supportUnitsByWork = DB::table('support_units')
            ->select('id', 'support_work_id', 'name')
            ->orderBy('name')
            ->get()
            ->groupBy('support_work_id');

        $globalPositionKeys = DB::table('positions')
            ->whereNull('support_unit_id')
            ->select('job_family_id', 'name')
            ->get()
            ->mapWithKeys(fn (object $position) => [$position->job_family_id.'|||'.$position->name => true]);

        $positionsBySupportUnit = DB::table('positions')
            ->whereNotNull('support_unit_id')
            ->select('support_unit_id', 'job_family_id', 'name')
            ->orderBy('name')
            ->get()
            ->reject(fn (object $position) => $globalPositionKeys->has($position->job_family_id.'|||'.$position->name))
            ->groupBy('support_unit_id');

        $supportUnitContextById = $supportDepartments
            ->flatMap(fn (object $department) => ($supportWorksByDepartment[$department->id] ?? collect())
                ->flatMap(fn (object $work) => ($supportUnitsByWork[$work->id] ?? collect())
                    ->mapWithKeys(fn (object $unit) => [
                        'unit:'.$unit->id => [
                            'key' => implode('|||', [$department->name, $work->name, $unit->name]),
                            'name' => $unit->name,
                        ],
                    ])))
            ->all();

        $supportOrg = $supportDepartments
            ->mapWithKeys(fn (object $department) => [
                $department->name => ($supportWorksByDepartment[$department->id] ?? collect())
                    ->map(fn (object $work) => [
                        'work' => $work->name,
                        'units' => ($supportUnitsByWork[$work->id] ?? collect())
                            ->map(fn (object $unit) => [
                                'key' => $supportUnitContextById['unit:'.$unit->id]['key'],
                                'name' => $unit->name,
                                'positions' => ($positionsBySupportUnit[$unit->id] ?? collect())->pluck('name')->values(),
                            ])->values(),
                    ])
                    ->values(),
            ])
            ->all();

        $jobFamiliesWithPositions = $jobFamilies->map(fn (object $family) => [
            'id' => $family->id,
            'name' => $family->name,
            'worklineName' => $family->workline_name,
            'positions' => ($globalPositionsByFamily[$family->id] ?? collect())
                ->pluck('name')
                ->values(),
        ]);

        return [
            'worklines' => collect(['สายวิชาการ', 'สายสนับสนุน']),
            'jobFamiliesByWorkline' => $jobFamiliesWithPositions
                ->groupBy('worklineName')
                ->map(fn ($families) => $families->mapWithKeys(fn (array $family) => [
                    $family['name'] => $family['positions'],
                ]))
                ->all(),
            'academicJobFamilies' => $jobFamiliesWithPositions
                ->where('worklineName', 'สายวิชาการ')
                ->pluck('name')
                ->values(),
            'supportJobFamilies' => $jobFamiliesWithPositions
                ->where('worklineName', 'สายสนับสนุน')
                ->pluck('name')
                ->values(),
            'adminJobFamilies' => $jobFamiliesWithPositions
                ->where('worklineName', 'สายงานบริหาร')
                ->pluck('name')
                ->values(),
            'supportPositionGroups' => $jobFamiliesWithPositions
                ->where('worklineName', 'สายสนับสนุน')
                ->mapWithKeys(fn (array $family) => [$family['name'] => $family['positions']])
                ->all(),
            'supportOrg' => $supportOrg,
            'positionLookup' => $jobFamilies
                ->flatMap(fn (object $family) => ($positionsByFamily[$family->id] ?? collect())
                    ->map(fn (object $position) => [
                        'id' => $position->id,
                        'name' => $position->name,
                        'jobFamilyName' => $family->name,
                        'worklineName' => $family->workline_name,
                        'supportUnitKey' => $position->support_unit_id
                            ? ($supportUnitContextById['unit:'.$position->support_unit_id]['key'] ?? null)
                            : null,
                        'supportUnitName' => $position->support_unit_id
                            ? ($supportUnitContextById['unit:'.$position->support_unit_id]['name'] ?? null)
                            : null,
                    ]))
                ->values(),
            'levels' => DB::table('levels')->orderBy('name')->pluck('name'),
            'levelsByWorkline' => DB::table('levels')
                ->leftJoin('worklines', 'levels.workline_id', '=', 'worklines.id')
                ->select('levels.name', 'worklines.name as workline_name')
                ->whereNull('levels.job_family_id')
                ->orderBy('levels.name')
                ->get()
                ->filter(fn (object $level) => $level->workline_name !== null)
                ->groupBy('workline_name')
                ->map(fn ($levels) => $levels->pluck('name')->values())
                ->all(),
            'levelExpectationsByWorkline' => DB::table('levels')
                ->leftJoin('worklines', 'levels.workline_id', '=', 'worklines.id')
                ->select('levels.name', 'levels.expected_level', 'worklines.name as workline_name')
                ->whereNull('levels.job_family_id')
                ->orderBy('levels.name')
                ->get()
                ->filter(fn (object $level) => $level->workline_name !== null)
                ->groupBy('workline_name')
                ->map(fn ($levels) => $levels->mapWithKeys(fn (object $level) => [
                    $level->name => $level->expected_level,
                ]))
                ->all(),
        ];
    }

    private function hrStructurePayload(): array
    {
        $structure = $this->adminStructurePayload();

        unset($structure['levelsByWorkline']);
        unset($structure['levelExpectationsByWorkline']);

        $structure['positionCompetencies'] = DB::table('position_competencies')
            ->select('assessment_round_id', 'position_id', 'competency_id')
            ->orderBy('competency_id')
            ->get()
            ->groupBy('assessment_round_id')
            ->map(fn ($roundItems) => $roundItems
                ->groupBy('position_id')
                ->map(fn ($items) => $items->pluck('competency_id')->values()))
            ->all();

        $structure['positionFcSelectionRules'] = Schema::hasTable('position_fc_selection_rules')
            ? DB::table('position_fc_selection_rules')
                ->select('assessment_round_id', 'position_id', 'required_fc_count')
                ->get()
                ->groupBy('assessment_round_id')
                ->map(fn ($roundRules) => $roundRules->mapWithKeys(fn (object $rule) => [
                    $rule->position_id => (int) $rule->required_fc_count,
                ]))
                ->all()
            : [];

        return $structure;
    }

    private function competencyPayload()
    {
        return DB::table('competencies')
            ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->select(
                'competencies.id',
                'competencies.competency_type_id',
                'competencies.code',
                'competencies.name',
                'competencies.detail',
                'competency_types.code as type_code',
                'competency_types.full_name as type_full_name'
            )
            ->orderBy('competencies.code')
            ->get()
            ->map(function (object $competency) {
                $levels = $this->competencyLevelsPayload((int) $competency->id);

                return [
                    'id' => $competency->id,
                    'competencyTypeId' => $competency->competency_type_id,
                    'cd' => $competency->code,
                    'n' => $competency->name,
                    't' => $competency->type_code,
                    'typeName' => $competency->type_full_name,
                    'tg' => 'tag-'.strtolower((string) $competency->type_code),
                    'det' => $competency->detail ?? '',
                    'lv' => count($levels),
                    'grp' => '',
                    'levels' => $levels,
                ];
            });
    }

    private function assignedCompetenciesForUser(User $user): array
    {
        $activeRoundId = $this->activeRoundId();
        if (! $activeRoundId) {
            return [];
        }

        $levelIds = collect();
        $positionIds = $this->positionIdsForUser($user);

        if ($user->level_id) {
            $levelIds->push($user->level_id);
        }

        foreach ([$user->level, $user->position] as $levelName) {
            if (! $levelName) {
                continue;
            }

            $worklineId = $this->worklineIdFromUser($user);

            $matchingLevels = DB::table('levels')
                ->where('name', $levelName)
                ->where(function ($query) use ($worklineId) {
                    $query->whereNull('workline_id');

                    if ($worklineId) {
                        $query->orWhere('workline_id', $worklineId);
                    }
                })
                ->pluck('id');

            $levelIds = $levelIds->merge($matchingLevels);
        }

        $levelIds = $levelIds->filter()->unique()->values();

        if ($levelIds->isEmpty() && $positionIds->isEmpty()) {
            return [];
        }

        $jobFamilyIds = collect();

        if ($user->position_id) {
            $positionFamilyId = DB::table('positions')->where('id', $user->position_id)->value('job_family_id');
            if ($positionFamilyId) {
                $jobFamilyIds->push($positionFamilyId);
            }
        }

        if ($user->workline) {
            $worklineId = $this->worklineIdFromUser($user);
            if ($worklineId) {
                $jobFamilyIds = $jobFamilyIds->merge(
                    DB::table('job_families')->where('workline_id', $worklineId)->pluck('id')
                );
            }
        }

        $jobFamilyIds = $jobFamilyIds->filter()->unique()->values();

        $expectedLevelResolver = app(ExpectedLevelResolver::class);

        $expectationRows = $levelIds->isEmpty()
            ? collect()
            : DB::table('hr_expectations')
                ->join('competencies', 'hr_expectations.competency_id', '=', 'competencies.id')
                ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
                ->leftJoin('levels', 'hr_expectations.level_id', '=', 'levels.id')
                ->whereIn('hr_expectations.level_id', $levelIds)
                ->when($activeRoundId, fn ($query) => $query->where('hr_expectations.assessment_round_id', $activeRoundId))
                ->when($jobFamilyIds->isNotEmpty(), fn ($query) => $query->whereIn('hr_expectations.job_family_id', $jobFamilyIds))
                ->select(
                    'competencies.id',
                    'competencies.competency_type_id',
                    'competencies.code',
                    'competencies.name',
                    'competencies.detail',
                    'competency_types.code as type_code',
                    DB::raw('COALESCE(hr_expectations.expected_level, levels.expected_level) as expected_level')
                )
                ->get();

        $positionRows = $positionIds->isEmpty()
            ? collect()
            : DB::table('position_competencies')
                ->join('competencies', 'position_competencies.competency_id', '=', 'competencies.id')
                ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
                ->whereIn('position_competencies.position_id', $positionIds)
                ->when($activeRoundId, fn ($query) => $query->where('position_competencies.assessment_round_id', $activeRoundId))
                ->select(
                    'competencies.id',
                    'competencies.competency_type_id',
                    'competencies.code',
                    'competencies.name',
                    'competencies.detail',
                    'competency_types.code as type_code',
                    DB::raw('NULL as expected_level')
                )
                ->get();

        $fcSelection = $this->fcTopicSelectionPayloadForUser($user);
        $selectedApprovedFcIds = collect($fcSelection['selectedCompetencyIds'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();
        $shouldFilterFc = (int) ($fcSelection['requiredCount'] ?? 0) > 0;

        return $expectationRows
            ->merge($positionRows)
            ->sortBy('code')
            ->unique('id')
            ->when($shouldFilterFc, fn ($items) => $items->filter(function (object $item) use ($selectedApprovedFcIds): bool {
                if (! str_starts_with((string) ($item->type_code ?? ''), 'FC')) {
                    return true;
                }

                return $selectedApprovedFcIds->contains((int) $item->id);
            }))
            ->map(function (object $item) use ($user, $expectedLevelResolver, $activeRoundId): array {
                $payload = $this->compactCompetencyPayload($item);
                $payload['expectedLevel'] = $payload['expectedLevel']
                    ?? $expectedLevelResolver->forUserCompetency($user, (int) $item->id);
                $assessment = DB::table('assessments')
                    ->where('user_id', $user->id)
                    ->where('competency_id', $item->id)
                    ->when($activeRoundId, fn ($query) => $query->where('assessment_round_id', $activeRoundId))
                    ->select('id', 'status', 'last_draft_saved_at')
                    ->first();
                $gapStatus = $assessment
                    ? DB::table('competency_gaps')
                        ->where('assessment_id', $assessment->id)
                        ->where('competency_id', $item->id)
                        ->value('status')
                    : null;

                $payload['assessmentStatus'] = $gapStatus ?? $assessment?->status ?? 'draft';
                $lastDraftSavedAt = $assessment?->last_draft_saved_at;
                $payload['lastDraftSavedAt'] = $lastDraftSavedAt
                    ? Carbon::parse($lastDraftSavedAt)->toISOString()
                    : null;

                return $payload;
            })
            ->values()
            ->all();
    }

    private function fcTopicSelectionPayloadForUser(User $user): array
    {
        $positionId = (int) ($user->position_id ?? 0);
        $roundId = $this->activeRoundId();

        if (! $roundId || $positionId <= 0 || ! Schema::hasTable('position_fc_selection_rules')) {
            return [
                'requiredCount' => 0,
                'status' => 'not_required',
                'availableCompetencies' => [],
                'selectedCompetencyIds' => [],
                'reviewComment' => '',
            ];
        }

        $requiredCount = (int) DB::table('position_fc_selection_rules')
            ->where('position_id', $positionId)
            ->when($roundId, fn ($query) => $query->where('assessment_round_id', $roundId))
            ->value('required_fc_count');

        $availableCompetencies = DB::table('position_competencies')
            ->join('competencies', 'position_competencies.competency_id', '=', 'competencies.id')
            ->join('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('position_competencies.position_id', $positionId)
            ->when($roundId, fn ($query) => $query->where('position_competencies.assessment_round_id', $roundId))
            ->whereIn('competency_types.code', ['FC', 'FC1', 'FC2'])
            ->select(
                'competencies.id',
                'competencies.competency_type_id',
                'competencies.code',
                'competencies.name',
                'competencies.detail',
                'competency_types.code as type_code',
                DB::raw('NULL as expected_level')
            )
            ->orderBy('competencies.code')
            ->get()
            ->map(fn (object $item): array => $this->compactCompetencyPayload($item))
            ->values()
            ->all();

        $selection = Schema::hasTable('fc_topic_selections')
            ? DB::table('fc_topic_selections')
                ->where('user_id', $user->id)
                ->where('position_id', $positionId)
                ->when($roundId, fn ($query) => $query->where('assessment_round_id', $roundId))
                ->first()
            : null;

        $selectedIds = $selection && Schema::hasTable('fc_topic_selection_items')
            ? DB::table('fc_topic_selection_items')
                ->where('fc_topic_selection_id', $selection->id)
                ->pluck('competency_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all()
            : [];

        return [
            'id' => $selection?->id,
            'requiredCount' => $requiredCount,
            'status' => $requiredCount > 0 ? ($selection?->status ?? 'draft') : 'not_required',
            'positionId' => $positionId,
            'availableCompetencies' => $availableCompetencies,
            'selectedCompetencyIds' => $selectedIds,
            'submittedTo' => $selection?->submitted_to,
            'submittedAt' => $selection?->submitted_at ? Carbon::parse($selection->submitted_at)->toISOString() : null,
            'reviewedBy' => $selection?->reviewed_by,
            'reviewComment' => $selection?->review_comment ?? '',
            'reviewedAt' => $selection?->reviewed_at ? Carbon::parse($selection->reviewed_at)->toISOString() : null,
        ];
    }

    private function idpReviewModuleForReviewer(User $reviewer): array
    {
        if (! Schema::hasTable('user_reviewer_steps')) {
            return ['enabled' => false, 'assignmentCount' => 0];
        }

        $query = DB::table('user_reviewer_steps')
            ->where('reviewer_id', $reviewer->id);

        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $query->where('chain_type', 'idp');
        }

        $assignmentCount = $query->distinct()->count('user_id');

        return [
            'enabled' => $assignmentCount > 0,
            'assignmentCount' => $assignmentCount,
        ];
    }

    private function fcTopicApprovalModuleForReviewer(User $reviewer): array
    {
        $roundId = $this->activeRoundId();

        if (! $roundId) {
            return ['enabled' => false, 'items' => []];
        }

        if (! Schema::hasTable('user_reviewer_steps')) {
            return ['enabled' => false, 'items' => []];
        }

        $stepQuery = DB::table('user_reviewer_steps')
            ->where('reviewer_id', $reviewer->id)
            ->where('step_order', 1);

        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $stepQuery->where('chain_type', 'assessment');
        }

        $employeeIds = $stepQuery->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();

        if ($employeeIds->isEmpty()) {
            return ['enabled' => false, 'items' => []];
        }

        if (! Schema::hasTable('fc_topic_selections')) {
            return ['enabled' => true, 'items' => []];
        }

        $employees = User::query()->whereIn('id', $employeeIds)->get()->keyBy('id');
        $selections = DB::table('fc_topic_selections')
            ->whereIn('user_id', $employeeIds)
            ->where('submitted_to', $reviewer->id)
            ->where('status', 'submitted')
            ->where('assessment_round_id', $roundId)
            ->orderBy('submitted_at')
            ->get();

        $selectionIds = $selections->pluck('id');
        $topicsBySelection = $selectionIds->isEmpty() || ! Schema::hasTable('fc_topic_selection_items')
            ? collect()
            : DB::table('fc_topic_selection_items')
                ->join('competencies', 'fc_topic_selection_items.competency_id', '=', 'competencies.id')
                ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
                ->whereIn('fc_topic_selection_items.fc_topic_selection_id', $selectionIds)
                ->orderBy('competencies.code')
                ->get([
                    'fc_topic_selection_items.fc_topic_selection_id as selection_id',
                    'competencies.id',
                    'competencies.competency_type_id',
                    'competencies.code',
                    'competencies.name',
                    'competencies.detail',
                    'competency_types.code as type_code',
                ])
                ->groupBy('selection_id');

        return [
            'enabled' => true,
            'items' => $selections->map(function (object $selection) use ($employees, $topicsBySelection): array {
                $employee = $employees->get($selection->user_id);
                $department = $employee ? $this->currentDepartmentForUser($employee) : '';
                $departmentParts = array_values(array_filter(array_map('trim', explode(' > ', $department))));
                $isSupport = $employee && ($this->usesSupportUnitStructure($employee)
                    || in_array($employee->workline, ['สายสนับสนุน', 'สายงานสนับสนุน'], true));
                $departmentDisplay = $isSupport
                    ? ($departmentParts[array_key_last($departmentParts)] ?? $department)
                    : ($departmentParts[0] ?? $department);

                return [
                    'id' => (int) $selection->id,
                    'employeeId' => (int) $selection->user_id,
                    'employeeName' => $employee ? trim(($employee->title ?: '').$employee->name) : '-',
                    'position' => $employee?->position ?: '-',
                    'department' => $departmentDisplay ?: '-',
                    'departmentLabel' => $isSupport ? 'หน่วยงาน' : 'ภาควิชา',
                    'submittedAt' => $selection->submitted_at ? Carbon::parse($selection->submitted_at)->toISOString() : null,
                    'topics' => collect($topicsBySelection->get($selection->id, []))->map(fn (object $topic): array => [
                        'id' => (int) $topic->id,
                        'code' => $topic->code,
                        'name' => $topic->name,
                        'detail' => $topic->detail ?: '',
                        'type' => $topic->type_code ?: 'FC',
                        'levels' => $this->competencyLevelsPayload((int) $topic->id),
                    ])->values()->all(),
                ];
            })->values()->all(),
        ];
    }

    private function assessmentApprovalModuleForReviewer(User $reviewer): array
    {
        if (! Schema::hasTable('user_reviewer_steps')) {
            return ['enabled' => false, 'items' => [], 'pendingCount' => 0];
        }

        $stepQuery = DB::table('user_reviewer_steps')
            ->where('reviewer_id', $reviewer->id)
            ->orderBy('step_order');

        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $stepQuery->where('chain_type', 'assessment');
        }

        $assignedSteps = $stepQuery->get(['user_id', 'step_order']);
        if ($assignedSteps->isEmpty()) {
            return ['enabled' => false, 'items' => [], 'pendingCount' => 0];
        }

        $employees = User::query()
            ->whereIn('id', $assignedSteps->pluck('user_id')->unique())
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        $items = $assignedSteps
            ->map(function (object $assigned) use ($employees): ?array {
                $employee = $employees->get($assigned->user_id);
                if (! $employee) {
                    return null;
                }

                $step = (int) $assigned->step_order;
                $expectedStatus = $this->reviewerChainResolver->pendingStatusForStep($step);
                $allCompetencies = collect($this->competencyGapsForUser($employee));
                $competencies = $allCompetencies
                    ->filter(fn (array $gap): bool => ($gap['status'] ?? 'draft') === $expectedStatus)
                    ->values()
                    ->all();
                $reviewedStatuses = ['revision_required', 'approved'];
                if ($step <= 1) {
                    $reviewedStatuses = [...$reviewedStatuses, 'unit_evaluated', 'dept_evaluated'];
                } elseif ($step === 2) {
                    $reviewedStatuses[] = 'dept_evaluated';
                }
                for ($nextStep = max(4, $step + 1); $nextStep <= 12; $nextStep++) {
                    $reviewedStatuses[] = 'review_step_'.$nextStep;
                }
                $reviewedCount = $allCompetencies
                    ->filter(fn (array $gap): bool => in_array($gap['status'] ?? 'draft', $reviewedStatuses, true))
                    ->count();
                $totalCompetencies = $allCompetencies->count();
                $hasRevision = $allCompetencies->contains(fn (array $gap): bool => ($gap['status'] ?? '') === 'revision_required');
                $allApproved = $totalCompetencies > 0
                    && $allCompetencies->every(fn (array $gap): bool => ($gap['status'] ?? '') === 'approved');
                $nextStatus = $this->reviewerChainResolver->nextStatusAfterStep($employee, $step);
                $hasForwarded = $allCompetencies->contains(fn (array $gap): bool => ($gap['status'] ?? '') === $nextStatus);

                $department = $this->currentDepartmentForUser($employee);
                $isSupport = $this->usesSupportUnitStructure($employee)
                    || in_array($employee->workline, ['สายสนับสนุน', 'สายงานสนับสนุน'], true);

                return [
                    'employeeId' => (int) $employee->id,
                    'employeeName' => $this->displayNameForUser($employee),
                    'position' => $employee->position ?: '-',
                    'organizationLabel' => $isSupport ? 'หน่วย' : 'ภาควิชา',
                    'organization' => $this->approvalOrganizationForUser($employee, $department) ?: '-',
                    'reviewStep' => $step,
                    'totalSteps' => count($this->reviewerChainResolver->stepsForUser($employee)),
                    'submittedAt' => $allCompetencies->pluck('updatedAt')->filter()->max(),
                    'competencies' => $competencies,
                    'allCompetencies' => $allCompetencies->values()->all(),
                    'statusLabel' => $totalCompetencies === 0
                        ? 'ยังไม่ประเมิน'
                        : 'ตรวจสอบแล้ว '.$reviewedCount.'/'.$totalCompetencies,
                    'statusClass' => $hasRevision ? 'br' : ($reviewedCount >= $totalCompetencies ? 'bg' : 'by'),
                    'isPending' => $competencies !== [],
                    'isForwarded' => $hasForwarded,
                    'isApproved' => $allApproved,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $item) => $item['submittedAt'] ?? '')
            ->values()
            ->all();

        return [
            'enabled' => true,
            'items' => $items,
            'pendingCount' => collect($items)->sum(fn (array $item): int => count($item['competencies'])),
            'pendingPeopleCount' => collect($items)->where('isPending', true)->count(),
            'forwardedCount' => collect($items)->where('isForwarded', true)->count(),
            'approvedCount' => collect($items)->where('isApproved', true)->count(),
        ];
    }

    private function positionIdsForUser(User $user): Collection
    {
        $positionIds = collect();

        if ($user->position_id) {
            $positionIds->push($user->position_id);
        }

        if (! $user->position) {
            return $positionIds->filter()->unique()->values();
        }

        $worklineId = $this->worklineIdFromUser($user);

        $matchedIds = DB::table('positions')
            ->join('job_families', 'positions.job_family_id', '=', 'job_families.id')
            ->where('positions.name', $user->position)
            ->when($worklineId, fn ($query) => $query->where('job_families.workline_id', $worklineId))
            ->when($user->department, function ($query) use ($user) {
                $departmentRoot = trim(explode(' > ', $user->department)[0] ?? $user->department);

                $query->where(function ($nested) use ($user, $departmentRoot) {
                    $nested->where('job_families.name', $user->department)
                        ->orWhere('job_families.name', $departmentRoot);
                });
            })
            ->pluck('positions.id');

        return $positionIds->merge($matchedIds)->filter()->unique()->values();
    }

    private function worklineIdFromUser(User $user): ?int
    {
        if (! $user->workline) {
            return null;
        }

        $workline = trim($user->workline);

        if (array_key_exists($workline, $this->worklineIdCache)) {
            return $this->worklineIdCache[$workline];
        }

        $withoutPrefix = preg_replace('/^สาย/u', '', $workline) ?: $workline;
        $candidates = collect([
            $workline,
            $withoutPrefix,
            'สาย'.$withoutPrefix,
            'สายงาน'.$withoutPrefix,
        ])->filter()->unique()->values();

        $id = DB::table('worklines')
            ->whereIn('name', $candidates)
            ->value('id');

        return $this->worklineIdCache[$workline] = $id ? (int) $id : null;
    }

    private function compactCompetencyPayload(object $competency): array
    {
        $levels = $this->competencyLevelsPayload((int) $competency->id);

        return [
            'id' => $competency->id,
            'competencyTypeId' => $competency->competency_type_id,
            'cd' => $competency->code,
            'n' => $competency->name,
            't' => $competency->type_code,
            'tg' => 'tag-'.strtolower((string) $competency->type_code),
            'det' => $competency->detail ?? '',
            'expectedLevel' => $competency->expected_level,
            'lv' => count($levels),
            'levels' => $levels,
        ];
    }

    private function competencyLevelsPayload(int $competencyId): array
    {
        if ($this->competencyLevelsByCompetency === null) {
            $levels = DB::table('competency_levels')
                ->orderBy('competency_id')
                ->orderBy('level')
                ->get();
            $indicatorsByLevel = DB::table('comp_level_indicators')
                ->whereIn('competency_level_id', $levels->pluck('id'))
                ->orderBy('id')
                ->get()
                ->groupBy('competency_level_id');

            $this->competencyLevelsByCompetency = $levels
                ->groupBy('competency_id')
                ->map(fn ($competencyLevels) => $competencyLevels
                    ->map(function (object $level) use ($indicatorsByLevel): array {
                        $indicators = $indicatorsByLevel[$level->id] ?? collect();

                        return [
                            'id' => $level->id,
                            'lvl' => $level->level,
                            'label' => "ระดับที่ {$level->level}",
                            'description' => $level->description ?? '',
                            'indicators' => $indicators->pluck('description')->values()->all(),
                            'weights' => $indicators->pluck('weight')
                                ->map(fn ($weight) => $weight === null ? null : (float) $weight)
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all())
                ->all();
        }

        return $this->competencyLevelsByCompetency[$competencyId] ?? [];
    }

    private function competencyGapsForUser(User $user): array
    {
        $expectedLevelResolver = app(ExpectedLevelResolver::class);
        $roundId = $this->activeRoundId()
            ?? (int) DB::table('assessment_rounds')->orderByDesc('year')->orderByDesc('id')->value('id');

        $gapRows = DB::table('assessments')
            ->join('competencies', 'assessments.competency_id', '=', 'competencies.id')
            ->leftJoin('competency_gaps', function ($join) {
                $join->on('competency_gaps.assessment_id', '=', 'assessments.id')
                    ->on('competency_gaps.competency_id', '=', 'assessments.competency_id');
            })
            ->leftJoin('scores as evaluator_scores', 'competency_gaps.supervisor_2_score_id', '=', 'evaluator_scores.id')
            ->leftJoin('users as rejecting_reviewer', 'competency_gaps.rejected_by', '=', 'rejecting_reviewer.id')
            ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('assessments.user_id', $user->id)
            ->when($roundId, fn ($query) => $query->where('assessments.assessment_round_id', $roundId))
            ->where(function ($query) {
                $query->whereNotNull('assessments.last_draft_saved_at')
                    ->orWhere('assessments.status', '<>', 'draft')
                    ->orWhereNotNull('competency_gaps.id');
            })
            ->select(
                'assessments.id as assessment_id',
                'competency_gaps.id as competency_gap_id',
                'competencies.id as competency_id',
                'competencies.code',
                'competencies.name',
                'competencies.detail',
                'competency_types.code as type_code',
                'assessments.score',
                'competency_gaps.expected_level',
                'competency_gaps.actual_level',
                'competency_gaps.gap',
                'competency_gaps.requires_idp',
                'competency_gaps.status',
                'competency_gaps.reject_comment',
                'competency_gaps.rejected_by',
                'rejecting_reviewer.title as reject_reviewer_title',
                'rejecting_reviewer.name as reject_reviewer_name',
                'assessments.note',
                'evaluator_scores.comment as evaluator_comment',
                'assessments.updated_at'
            )
            ->orderBy('competencies.code')
            ->get();

        $reviewHistoryByAssessmentCompetency = DB::table('scores')
            ->join('users as score_reviewers', 'scores.assessor_id', '=', 'score_reviewers.id')
            ->whereIn('scores.assessment_id', $gapRows->pluck('assessment_id')->filter()->unique()->values())
            ->where('scores.assessor_role', 'like', 'supervisor_%')
            ->whereIn('scores.status', ['approved', 'rejected'])
            ->select(
                'scores.assessment_id',
                'scores.competency_id',
                'scores.assessor_id',
                'scores.assessor_role',
                'scores.comment',
                'scores.status',
                'scores.submitted_at',
                'scores.updated_at',
                'score_reviewers.title as reviewer_title',
                'score_reviewers.name as reviewer_name',
                'score_reviewers.position as reviewer_position',
            )
            ->get()
            ->groupBy(fn (object $score): string => $score->assessment_id.':'.$score->competency_id)
            ->map(fn ($scores): array => $scores
                ->map(function (object $score): array {
                    preg_match('/^supervisor_(\d+)$/', (string) $score->assessor_role, $matches);

                    return [
                        'reviewerId' => (int) $score->assessor_id,
                        'reviewerName' => trim(
                            (string) ($score->reviewer_title ?? '')
                            .(string) ($score->reviewer_name ?? '')
                        ),
                        'reviewerPosition' => $score->reviewer_position ?? '',
                        'reviewStep' => isset($matches[1]) ? (int) $matches[1] : null,
                        'comment' => trim((string) $score->comment),
                        'decision' => $score->status,
                        'submittedAt' => $score->submitted_at ?? $score->updated_at,
                    ];
                })
                ->sortBy(fn (array $comment): string => sprintf(
                    '%04d-%s',
                    $comment['reviewStep'] ?? 9999,
                    (string) $comment['submittedAt'],
                ))
                ->values()
                ->all());

        return $gapRows
            ->map(function (object $gap) use ($user, $expectedLevelResolver, $reviewHistoryByAssessmentCompetency): array {
                $checkedIndicatorKeys = DB::table('assessment_indicator_results')
                    ->where('assessment_id', $gap->assessment_id)
                    ->where('competency_id', $gap->competency_id)
                    ->where('is_checked', true)
                    ->pluck('indicator_key')
                    ->values()
                    ->all();
                $expected = $gap->expected_level === null
                    ? $expectedLevelResolver->forUserCompetency($user, (int) $gap->competency_id)
                    : (float) $gap->expected_level;
                $actual = $gap->actual_level === null ? (float) $gap->score : (float) $gap->actual_level;
                $gapValue = $gap->gap === null && $expected !== null
                    ? round($actual - (float) $expected, 2)
                    : ($gap->gap === null ? null : (float) $gap->gap);

                $status = $gap->status === 'dean_approved' ? 'approved' : ($gap->status ?? 'draft');
                $rejectReviewerName = trim(
                    (string) ($gap->reject_reviewer_title ?? '')
                    .(string) ($gap->reject_reviewer_name ?? '')
                );

                return [
                    'id' => (int) $gap->competency_gap_id,
                    'competencyId' => (int) $gap->competency_id,
                    'cd' => $gap->code,
                    'n' => $gap->name,
                    't' => $gap->type_code ?: '-',
                    'tg' => 'tag-'.strtolower((string) ($gap->type_code ?: 'cc')),
                    'det' => $gap->detail ?? '',
                    'expected' => $expected,
                    'actual' => $actual,
                    'gap' => $gapValue,
                    'note' => $gap->note ?? '',
                    'reviewerComment' => $gap->evaluator_comment ?? '',
                    'reviewHistory' => $reviewHistoryByAssessmentCompetency
                        ->get($gap->assessment_id.':'.$gap->competency_id, []),
                    'reviewerComments' => collect($reviewHistoryByAssessmentCompetency
                        ->get($gap->assessment_id.':'.$gap->competency_id, []))
                        ->filter(fn (array $review): bool => $review['comment'] !== '')
                        ->values()
                        ->all(),
                    'rejectComment' => $gap->reject_comment ?? '',
                    'rejectReviewerId' => $gap->rejected_by ? (int) $gap->rejected_by : null,
                    'rejectReviewerName' => $rejectReviewerName,
                    'levels' => $this->competencyLevelsPayload((int) $gap->competency_id),
                    'checkedIndicatorKeys' => $checkedIndicatorKeys,
                    'checkedIndicatorCount' => count($checkedIndicatorKeys),
                    'requiresIdp' => $gapValue !== null && $gapValue < 0,
                    'missingIndicators' => $gapValue !== null && $gapValue < 0 && $expected !== null
                        ? $this->missingIndicatorsForAssessment((int) $gap->assessment_id, (int) $gap->competency_id, (float) $expected, $actual)
                        : [],
                    'missingIndicatorCount' => $gapValue !== null && $gapValue < 0
                        ? (int) ceil(abs($gapValue) / 0.25)
                        : 0,
                    'status' => $status,
                    'workflow' => $this->assessmentWorkflowPayload(
                        $user,
                        $status,
                        $gap->rejected_by ? (int) $gap->rejected_by : null,
                        $rejectReviewerName,
                    ),
                    'updatedAt' => $gap->updated_at,
                ];
            })
            ->values()
            ->all();
    }

    private function assessmentWorkflowPayload(
        User $user,
        string $status,
        ?int $rejectReviewerId = null,
        string $rejectReviewerName = '',
    ): array {
        $status = $status === 'dean_approved' ? 'approved' : ($status ?: 'draft');
        $reviewerSteps = $this->assessmentReviewerStepsCache[$user->id]
            ??= $this->reviewerChainResolver->payloadForUser($user);
        $currentReviewer = collect($reviewerSteps)->first(
            fn (array $step): bool => $this->reviewerChainResolver->pendingStatusForStep((int) $step['step']) === $status
        );
        $rejectReviewer = $rejectReviewerId
            ? collect($reviewerSteps)->first(fn (array $step): bool => (int) $step['id'] === $rejectReviewerId)
            : null;
        $rejectStep = $rejectReviewer ? (int) $rejectReviewer['step'] : null;

        if ($status === 'draft') {
            $key = 'self_pending';
            $label = 'ยังไม่ประเมินตนเอง';
        } elseif ($status === 'revision_required') {
            $key = 'revision_required';
            $label = $rejectReviewerName !== ''
                ? 'ถูกส่งกลับโดย '.$rejectReviewerName
                : 'ถูกส่งกลับแก้ไข';
        } elseif ($status === 'approved') {
            $key = 'approved';
            $label = 'ผ่านครบทุกลำดับ';
        } elseif ($currentReviewer) {
            $key = 'pending_review';
            $label = 'รอการประเมินลำดับที่ '.(int) $currentReviewer['step'].' · '.$currentReviewer['name'];
        } else {
            $key = 'pending_review';
            $label = 'อยู่ระหว่างการประเมิน';
        }

        $timeline = [[
            'kind' => 'self',
            'step' => 0,
            'label' => 'ประเมินตนเอง',
            'name' => $this->displayNameForUser($user),
            'position' => $user->position ?: '',
            'state' => in_array($status, ['draft', 'revision_required'], true) ? 'active' : 'complete',
        ]];

        foreach ($reviewerSteps as $reviewerStep) {
            $step = (int) $reviewerStep['step'];
            $state = 'waiting';

            if ($status === 'approved') {
                $state = 'complete';
            } elseif ($currentReviewer) {
                $currentStep = (int) $currentReviewer['step'];
                $state = $step < $currentStep ? 'complete' : ($step === $currentStep ? 'active' : 'waiting');
            } elseif ($status === 'revision_required' && $rejectStep !== null) {
                $state = $step < $rejectStep ? 'complete' : ($step === $rejectStep ? 'returned' : 'waiting');
            }

            $timeline[] = [
                'kind' => 'reviewer',
                'step' => $step,
                'label' => 'ผู้ประเมินลำดับที่ '.$step,
                'name' => $reviewerStep['name'],
                'position' => $reviewerStep['position'] ?? '',
                'reviewerId' => (int) $reviewerStep['id'],
                'state' => $state,
            ];
        }

        return [
            'key' => $key,
            'label' => $label,
            'currentStep' => $currentReviewer ? (int) $currentReviewer['step'] : null,
            'currentReviewerId' => $currentReviewer ? (int) $currentReviewer['id'] : null,
            'currentReviewerName' => $currentReviewer['name'] ?? null,
            'totalSteps' => count($reviewerSteps),
            'timeline' => $timeline,
        ];
    }

    private function missingIndicatorsForAssessment(int $assessmentId, int $competencyId, float $expectedLevel, float $actualLevel): array
    {
        $checkedKeys = DB::table('assessment_indicator_results')
            ->where('assessment_id', $assessmentId)
            ->where('competency_id', $competencyId)
            ->where('is_checked', true)
            ->pluck('indicator_key')
            ->flip();
        $maxExpectedLevel = max(1, (int) ceil($expectedLevel));
        $requiredIndicatorCount = max(0, (int) ceil(($expectedLevel - $actualLevel) / 0.25));

        if ($requiredIndicatorCount === 0) {
            return [];
        }

        $missingRows = DB::table('competency_levels')
            ->where('competency_id', $competencyId)
            ->where('level', '<=', $maxExpectedLevel)
            ->orderBy('level')
            ->get()
            ->flatMap(function (object $level) use ($checkedKeys, $competencyId) {
                return DB::table('comp_level_indicators')
                    ->where('competency_level_id', $level->id)
                    ->orderBy('id')
                    ->get()
                    ->values()
                    ->filter(function (object $indicator, int $index) use ($checkedKeys, $competencyId, $level): bool {
                        return ! $checkedKeys->has($competencyId.':'.$level->id.':'.$index);
                    })
                    ->map(fn (object $indicator, int $index): array => [
                        'level' => (int) $level->level,
                        'levelLabel' => 'ระดับ '.$level->level,
                        'code' => $level->level.'.'.($index + 1),
                        'description' => $indicator->description,
                    ])
                    ->values();
            })
            ->take($requiredIndicatorCount)
            ->values();

        return $missingRows
            ->groupBy('level')
            ->map(function ($indicators, int $level): array {
                return [
                    'level' => $level,
                    'label' => $indicators->first()['levelLabel'] ?? 'ระดับ '.$level,
                    'indicators' => $indicators
                        ->map(fn (array $indicator): array => [
                            'code' => $indicator['code'],
                            'description' => $indicator['description'],
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function currentUserIdpPayload(User $user): ?array
    {
        $round = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first(['id', 'year']);
        $roundId = $round?->id ? (int) $round->id : null;

        $idp = DB::table('idps')
            ->where('user_id', $user->id)
            ->when($roundId, function ($query) use ($roundId): void {
                $query->where(function ($roundQuery) use ($roundId): void {
                    $roundQuery->where('assessment_round_id', $roundId)
                        ->orWhere(function ($legacy) use ($roundId): void {
                            $legacy->whereNull('assessment_round_id')
                                ->whereExists(function ($linkedAssessment) use ($roundId): void {
                                    $linkedAssessment->selectRaw('1')
                                        ->from('idp_items')
                                        ->join('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
                                        ->join('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
                                        ->whereColumn('idp_items.idp_id', 'idps.id')
                                        ->where('assessments.assessment_round_id', $roundId);
                                });
                        });
                });
            })
            ->orderByDesc('id')
            ->first();

        if (! $idp) {
            return null;
        }

        $items = DB::table('idp_items')
            ->where('idp_items.idp_id', $idp->id)
            ->select(
                'idp_items.id',
                'idp_items.competency_gap_id',
                'idp_items.goal',
                'idp_items.success_criteria',
                'idp_items.status',
                'idp_items.submission_version',
                'idp_items.current_review_step',
                'idp_items.submitted_at',
                'idp_items.approved_at',
                'idp_items.reject_comment'
            )
            ->orderBy('idp_items.id')
            ->get();
        $latestRejectionsByItem = DB::table('idp_item_reviews')
            ->join('users', 'idp_item_reviews.reviewer_id', '=', 'users.id')
            ->whereIn('idp_item_reviews.idp_item_id', $items->pluck('id'))
            ->where('idp_item_reviews.decision', 'rejected')
            ->select(
                'idp_item_reviews.id',
                'idp_item_reviews.idp_item_id',
                'idp_item_reviews.submission_version',
                'users.name as reviewer_name',
                'users.title as reviewer_title'
            )
            ->orderByDesc('idp_item_reviews.submission_version')
            ->orderByDesc('idp_item_reviews.id')
            ->get()
            ->unique('idp_item_id')
            ->keyBy('idp_item_id');
        $activityColumns = [
            'idp_activities.id',
            'idp_activities.idp_item_id',
            'idp_activities.learning_catalog_id',
            'idp_activities.idp_learning_method_id',
            'idp_activities.activity_name',
            'idp_activities.weight_percent',
            'idp_activities.start_date',
            'idp_activities.end_date',
            'idp_activities.description as activity_description',
            'idp_activities.document_reference_number',
            'learning_method_types.key as method_key',
        ];

        if (Schema::hasColumn('idp_activities', 'form_code')) {
            $activityColumns[] = 'idp_activities.form_code';
        }

        if (Schema::hasColumn('idp_activities', 'form_details')) {
            $activityColumns[] = 'idp_activities.form_details';
        }

        $activitiesByItem = DB::table('idp_activities')
            ->leftJoin('learning_method_types', 'idp_activities.method_type_id', '=', 'learning_method_types.id')
            ->whereIn('idp_activities.idp_item_id', $items->pluck('id'))
            ->select($activityColumns)
            ->orderBy('idp_activities.id')
            ->get()
            ->groupBy('idp_item_id');

        $payloadItems = $items
            ->groupBy('competency_gap_id')
            ->map(function ($legacyItems) use ($activitiesByItem, $latestRejectionsByItem): array {
                $latest = $legacyItems->sortByDesc('id')->first();
                $latestRejection = $latestRejectionsByItem->get($latest->id);

                return [
                    'id' => (int) $latest->id,
                    'competencyGapId' => (int) $latest->competency_gap_id,
                    'goal' => $latest->goal ?? '',
                    'successCriteria' => $latest->success_criteria ?? '',
                    'status' => $latest->status ?? 'draft',
                    'submissionVersion' => (int) ($latest->submission_version ?? 0),
                    'currentReviewStep' => $latest->current_review_step
                        ? (int) $latest->current_review_step
                        : null,
                    'submittedAt' => $latest->submitted_at,
                    'approvedAt' => $latest->approved_at,
                    'rejectComment' => $latest->reject_comment ?? '',
                    'rejectReviewerName' => $latestRejection
                        ? trim(($latestRejection->reviewer_title ?: '').$latestRejection->reviewer_name)
                        : '',
                    'activities' => collect($activitiesByItem[$latest->id] ?? [])
                        ->map(fn (object $activity): array => [
                            'id' => (int) $activity->id,
                            'methodKey' => $activity->method_key ?? '',
                            'developmentToolId' => $activity->idp_learning_method_id
                                ? (int) $activity->idp_learning_method_id
                                : null,
                            'learningCatalogId' => $activity->learning_catalog_id
                                ? (int) $activity->learning_catalog_id
                                : null,
                            'activityName' => $activity->activity_name ?? '',
                            'activityDescription' => $activity->activity_description ?? '',
                            'documentReferenceNumber' => $activity->document_reference_number ?? '',
                            'weightPercent' => $activity->weight_percent === null
                                ? ''
                                : (float) $activity->weight_percent,
                            'startDate' => $activity->start_date ?? '',
                            'endDate' => $activity->end_date ?? '',
                            'formCode' => $activity->form_code ?? '',
                            'formDetails' => $this->decodeJsonObject($activity->form_details ?? null),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => (int) $idp->id,
            'year' => (int) $idp->year,
            'status' => $idp->status,
            'submittedAt' => $idp->submitted_at,
            'items' => $payloadItems,
        ];
    }

    private function idpReviewItemsForReviewer(User $reviewer, ?int $assessmentRoundId): array
    {
        if (! $assessmentRoundId) {
            return [];
        }

        $items = DB::table('idp_items')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->join('users', 'idps.user_id', '=', 'users.id')
            ->join('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->join('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->where('assessments.assessment_round_id', $assessmentRoundId)
            ->whereExists(function ($query) use ($reviewer): void {
                $query->selectRaw('1')->from('user_reviewer_steps')
                    ->whereColumn('user_reviewer_steps.user_id', 'users.id')
                    ->where('user_reviewer_steps.reviewer_id', $reviewer->id)
                    ->where('user_reviewer_steps.chain_type', 'idp');
            })
            ->select(
                'idp_items.id',
                'idp_items.goal',
                'idp_items.success_criteria',
                'idp_items.submission_version',
                'idp_items.current_review_step',
                'idp_items.status',
                'idps.user_id as owner_id',
                'idp_items.submitted_at',
                'users.sso as user_sso',
                'users.name as user_name',
                'users.title as user_title',
                'users.position as user_position',
                'users.department as user_department',
                'competencies.code as competency_code',
                'competencies.name as competency_name',
                'competency_gaps.gap'
            )
            ->orderBy('idp_items.submitted_at')
            ->get();

        $activities = DB::table('idp_activities')
            ->leftJoin('learning_method_types', 'idp_activities.method_type_id', '=', 'learning_method_types.id')
            ->whereIn('idp_activities.idp_item_id', $items->pluck('id'))
            ->select(
                'idp_activities.id',
                'idp_activities.idp_item_id',
                'idp_activities.activity_name',
                'idp_activities.weight_percent',
                'idp_activities.start_date',
                'idp_activities.end_date',
                'idp_activities.document_reference_number',
                'idp_activities.form_code',
                'idp_activities.form_details',
                'learning_method_types.label as method_label'
            )
            ->orderBy('idp_activities.id')
            ->get()
            ->groupBy('idp_item_id');
        $history = DB::table('idp_item_reviews')
            ->join('users', 'idp_item_reviews.reviewer_id', '=', 'users.id')
            ->whereIn('idp_item_reviews.idp_item_id', $items->pluck('id'))
            ->select(
                'idp_item_reviews.idp_item_id',
                'idp_item_reviews.submission_version',
                'idp_item_reviews.review_step',
                'idp_item_reviews.decision',
                'idp_item_reviews.comment',
                'idp_item_reviews.decided_at',
                'users.name as reviewer_name',
                'users.title as reviewer_title'
            )
            ->orderByDesc('idp_item_reviews.submission_version')
            ->orderBy('idp_item_reviews.review_step')
            ->get()
            ->groupBy('idp_item_id');

        $reviewerStepsByOwner = User::query()
            ->whereIn('id', $items->pluck('owner_id')->unique())
            ->get()
            ->mapWithKeys(fn (User $owner): array => [
                $owner->id => $this->reviewerChainResolver->payloadForUser($owner, 'idp'),
            ]);

        return $items->map(function (object $item) use ($activities, $history, $reviewer, $reviewerStepsByOwner): array {
            $reviewerSteps = collect($reviewerStepsByOwner->get((int) $item->owner_id, []));
            $currentReviewer = $reviewerSteps->firstWhere('step', (int) $item->current_review_step);
            $latestRejection = ($history[$item->id] ?? collect())
                ->first(fn (object $review): bool => $review->decision === 'rejected');

            return [
                'id' => (int) $item->id,
                'userId' => (int) $item->owner_id,
                'userSso' => $item->user_sso ?: '',
                'userName' => trim(($item->user_title ?: '').$item->user_name),
                'userPosition' => $item->user_position ?: '',
                'userDepartment' => $item->user_department ?: '',
                'competencyCode' => $item->competency_code,
                'competencyName' => $item->competency_name,
                'gap' => $item->gap === null ? null : (float) $item->gap,
                'goal' => $item->goal ?: '',
                'successCriteria' => $item->success_criteria ?: '',
                'submissionVersion' => (int) $item->submission_version,
                'currentReviewStep' => (int) $item->current_review_step,
                'currentReviewerId' => $currentReviewer ? (int) $currentReviewer['id'] : null,
                'currentReviewerName' => $currentReviewer['name'] ?? null,
                'rejectReviewerName' => $latestRejection
                    ? trim(($latestRejection->reviewer_title ?: '').$latestRejection->reviewer_name)
                    : null,
                'status' => $item->status,
                'canReview' => $reviewerSteps
                    ->contains(fn (array $step): bool => (int) $step['id'] === $reviewer->id
                        && $step['step'] === (int) $item->current_review_step
                        && $item->status === 'review_step_'.$step['step']),
                'submittedAt' => $item->submitted_at,
                'activities' => ($activities[$item->id] ?? collect())
                    ->map(fn (object $activity): array => [
                        'id' => (int) $activity->id,
                        'name' => $activity->activity_name ?: '',
                        'methodLabel' => $activity->method_label ?: '',
                        'weightPercent' => (float) ($activity->weight_percent ?? 0),
                        'startDate' => $activity->start_date ?: '',
                        'endDate' => $activity->end_date ?: '',
                        'documentReferenceNumber' => $activity->document_reference_number ?: '',
                        'formCode' => $activity->form_code ?: '',
                        'formDetails' => $this->decodeJsonObject($activity->form_details ?? null),
                    ])
                    ->values()
                    ->all(),
                'reviewHistory' => ($history[$item->id] ?? collect())
                    ->map(fn (object $review): array => [
                        'submissionVersion' => (int) $review->submission_version,
                        'reviewStep' => (int) $review->review_step,
                        'reviewerName' => trim(($review->reviewer_title ?: '').$review->reviewer_name),
                        'decision' => $review->decision,
                        'comment' => $review->comment ?: '',
                        'decidedAt' => $review->decided_at,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
    }

    private function idpActivityProgressReviewItems(
        User $reviewer,
        ?array $visibleUserIds = null,
        ?int $assessmentRoundId = null,
    ): array {
        $assessmentRoundId ??= $this->activeRoundId();
        if (! $assessmentRoundId) {
            return [];
        }

        $assignments = $visibleUserIds === null
            ? DB::table('user_reviewer_steps')
                ->where('reviewer_id', $reviewer->id)
                ->where('chain_type', 'idp')
                ->pluck('step_order', 'user_id')
            : collect();
        $userIds = $visibleUserIds === null
            ? $assignments->keys()->map(fn ($id): int => (int) $id)->values()
            : collect($visibleUserIds)->map(fn ($id): int => (int) $id)->unique()->values();
        if ($userIds->isEmpty()) {
            return [];
        }

        $latestItemIds = DB::table('idp_items')
            ->whereNotNull('competency_gap_id')
            ->selectRaw('competency_gap_id, MAX(id) as item_id')
            ->groupBy('competency_gap_id');

        $items = DB::table('competency_gaps')
            ->join('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->join('users', 'assessments.user_id', '=', 'users.id')
            ->leftJoinSub($latestItemIds, 'latest_idp_items', function ($join): void {
                $join->on('latest_idp_items.competency_gap_id', '=', 'competency_gaps.id');
            })
            ->leftJoin('idp_items', 'latest_idp_items.item_id', '=', 'idp_items.id')
            ->leftJoin('idp_item_completion_submissions', 'idp_items.id', '=', 'idp_item_completion_submissions.idp_item_id')
            ->whereIn('assessments.user_id', $userIds)
            ->when($assessmentRoundId, fn ($query) => $query->where('assessments.assessment_round_id', $assessmentRoundId))
            ->where('competency_gaps.requires_idp', true)
            ->where('competency_gaps.gap', '<', 0)
            ->whereIn('competency_gaps.status', ['approved', 'dean_approved'])
            ->whereIn('assessments.status', ['approved', 'dean_approved'])
            ->select(
                'competency_gaps.id as gap_id',
                'idp_items.id as item_id',
                'idp_items.status as plan_status',
                'idp_items.goal',
                'idp_items.success_criteria',
                'competencies.code as competency_code',
                'competencies.name as competency_name',
                'users.id as user_id',
                'users.title as user_title',
                'users.name as user_name',
                'users.position as user_position',
                'users.department as user_department',
                'idp_item_completion_submissions.public_id as completion_public_id',
                'idp_item_completion_submissions.status as completion_status',
                'idp_item_completion_submissions.result as completion_result',
                'idp_item_completion_submissions.current_review_step',
                'idp_item_completion_submissions.submission_version',
                'idp_item_completion_submissions.submitted_at',
                'idp_item_completion_submissions.updated_at as completion_updated_at'
            )
            ->orderBy('users.name')
            ->orderBy('competencies.code')
            ->get();

        $approvedItemIds = $items
            ->filter(fn (object $item): bool => $item->item_id !== null && $item->plan_status === 'approved')
            ->pluck('item_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $activities = $this->progressActivitiesForItems($approvedItemIds, false);

        $completionIds = DB::table('idp_item_completion_submissions')
            ->whereIn('idp_item_id', $items->pluck('item_id')->filter())
            ->pluck('id', 'idp_item_id');
        $history = DB::table('idp_item_completion_reviews')
            ->leftJoin('users', 'idp_item_completion_reviews.reviewer_id', '=', 'users.id')
            ->whereIn('idp_item_completion_reviews.completion_submission_id', $completionIds->values())
            ->select(
                'idp_item_completion_reviews.completion_submission_id',
                'idp_item_completion_reviews.submission_version',
                'idp_item_completion_reviews.review_step',
                'idp_item_completion_reviews.decision',
                'idp_item_completion_reviews.comment',
                'idp_item_completion_reviews.review_data',
                'idp_item_completion_reviews.decided_at',
                'users.title as reviewer_title',
                'users.name as reviewer_name'
            )
            ->orderBy('idp_item_completion_reviews.id')
            ->get()
            ->groupBy('completion_submission_id');

        $reviewerStepsByOwner = User::query()
            ->whereIn('id', $items->pluck('user_id')->unique())
            ->get()
            ->mapWithKeys(fn (User $owner): array => [
                $owner->id => $this->reviewerChainResolver->payloadForUser($owner, 'idp'),
            ]);

        return $items->map(function (object $item) use ($assignments, $activities, $completionIds, $history, $reviewerStepsByOwner, $visibleUserIds): array {
            $itemId = $item->item_id ? (int) $item->item_id : null;
            $completionId = $itemId ? ($completionIds[$itemId] ?? null) : null;
            $itemActivities = $itemId ? ($activities[$itemId] ?? []) : [];
            $completionStatus = $item->completion_status ?: 'in_progress';
            $schedule = $this->idpSchedulePayload($itemActivities, $completionStatus, $item->submitted_at);
            $reviewerStep = (int) ($assignments[(int) $item->user_id] ?? 0);
            $firstReviewerStep = (int) (collect($reviewerStepsByOwner->get((int) $item->user_id, []))->min('step') ?? 0);
            $currentReviewer = $item->current_review_step
                ? collect($reviewerStepsByOwner->get((int) $item->user_id, []))
                    ->firstWhere('step', (int) $item->current_review_step)
                : null;

            return [
                'id' => $itemId ?? 'gap-'.(int) $item->gap_id,
                'itemId' => $itemId,
                'competencyGapId' => (int) $item->gap_id,
                'planStatus' => $item->plan_status ?: 'not_started',
                'completionPublicId' => $item->completion_public_id,
                'completionStatus' => $completionStatus,
                'completionResult' => $item->completion_result,
                'submissionVersion' => (int) ($item->submission_version ?? 0),
                'currentReviewStep' => $item->current_review_step ? (int) $item->current_review_step : null,
                'currentReviewerName' => $currentReviewer['name'] ?? null,
                'canReview' => $visibleUserIds === null
                    && filled($item->completion_public_id)
                    && $reviewerStep === $firstReviewerStep
                    && $item->completion_status === 'review_step_'.$reviewerStep,
                'userId' => (int) $item->user_id,
                'userName' => trim(($item->user_title ?: '').$item->user_name),
                'userPosition' => $item->user_position ?: $item->user_department ?: '',
                'competencyCode' => $item->competency_code,
                'competencyName' => $item->competency_name,
                'goal' => $item->goal ?? '',
                'successCriteria' => $item->success_criteria ?? '',
                'submittedAt' => $this->isoUtcTimestamp($item->submitted_at),
                'completionUpdatedAt' => $this->isoUtcTimestamp($item->completion_updated_at),
                ...$schedule,
                'activities' => $itemActivities,
                'reviewHistory' => ($completionId ? ($history[$completionId] ?? collect()) : collect())->map(fn (object $review): array => [
                    'submissionVersion' => (int) $review->submission_version,
                    'reviewStep' => (int) $review->review_step,
                    'reviewerName' => trim(($review->reviewer_title ?: '').$review->reviewer_name),
                    'decision' => $review->decision,
                    'comment' => $review->comment ?? '',
                    'reviewData' => $this->decodeJsonObject($review->review_data ?? null),
                    'decidedAt' => $this->isoUtcTimestamp($review->decided_at),
                ])->values()->all(),
            ];
        })->sort(function (array $left, array $right): int {
            if ($left['isOverdue'] !== $right['isOverdue']) {
                return $left['isOverdue'] ? -1 : 1;
            }

            return [$left['dueDate'] ?: '9999-12-31', $left['userName'], $left['competencyCode']]
                <=> [$right['dueDate'] ?: '9999-12-31', $right['userName'], $right['competencyCode']];
        })->values()->all();
    }

    private function idpSchedulePayload(array $activities, string $completionStatus, ?string $submittedAt): array
    {
        $dueDate = collect($activities)
            ->pluck('endDate')
            ->filter(fn ($date): bool => filled($date))
            ->map(fn ($date): string => substr((string) $date, 0, 10))
            ->sort()
            ->last();

        if (! $dueDate) {
            return [
                'dueDate' => null,
                'isOverdue' => false,
                'daysOverdue' => 0,
                'submittedLateByDays' => 0,
            ];
        }

        $timezone = config('app.timezone', 'Asia/Bangkok');
        $deadline = Carbon::createFromFormat('Y-m-d', $dueDate, $timezone)->startOfDay();
        $today = Carbon::today($timezone);
        $isWaitingForReview = preg_match('/^review_step_\d+$/', $completionStatus) === 1;
        $employeeStillOwnsAction = ! $isWaitingForReview && $completionStatus !== 'approved';
        $isOverdue = $employeeStillOwnsAction && $today->greaterThan($deadline);
        $submittedDate = $submittedAt ? Carbon::parse($submittedAt)->setTimezone($timezone)->startOfDay() : null;

        return [
            'dueDate' => $dueDate,
            'isOverdue' => $isOverdue,
            'daysOverdue' => $isOverdue ? $deadline->diffInDays($today) : 0,
            'submittedLateByDays' => $submittedDate?->greaterThan($deadline)
                ? $deadline->diffInDays($submittedDate)
                : 0,
        ];
    }

    private function currentUserApprovedIdpActivities(User $user, ?int $assessmentRoundId): array
    {
        if (! $assessmentRoundId) {
            return [];
        }

        $activityRows = DB::table('idp_activities')
            ->join('idp_items', 'idp_activities.idp_item_id', '=', 'idp_items.id')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->join('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->join('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->where('idps.user_id', $user->id)
            ->where('assessments.assessment_round_id', $assessmentRoundId)
            ->where('idp_items.status', 'approved')
            ->select(
                'idp_activities.id',
                'idp_activities.idp_item_id',
                'idp_activities.activity_name',
                'idp_activities.weight_percent',
                'idp_activities.start_date',
                'idp_activities.end_date',
                'idp_activities.document_reference_number',
                'idp_activities.form_code',
                'idp_activities.form_details',
                'idp_activities.status as activity_status',
                'idp_items.goal',
                'idp_items.success_criteria',
                'competency_gaps.expected_level',
                'competency_gaps.actual_level',
                'competency_gaps.gap',
                'competencies.code as competency_code',
                'competencies.name as competency_name'
            )
            ->orderBy('competencies.code')
            ->orderBy('idp_activities.id')
            ->get();

        $progress = $this->progressActivitiesForItems(
            $activityRows->pluck('idp_item_id')->unique()->map(fn ($id): int => (int) $id)->all(),
            false
        );
        $progressByActivity = collect($progress)->flatten(1)->keyBy('id');

        return $activityRows->map(function (object $activity) use ($progressByActivity): array {
            $activityProgress = $progressByActivity[(int) $activity->id] ?? [];

            return [
                'id' => (int) $activity->id,
                'idpItemId' => (int) $activity->idp_item_id,
                'competencyCode' => $activity->competency_code,
                'competencyName' => $activity->competency_name,
                'name' => $activity->activity_name,
                'weightPercent' => (float) ($activity->weight_percent ?? 0),
                'startDate' => $activity->start_date,
                'endDate' => $activity->end_date,
                'documentReferenceNumber' => $activity->document_reference_number ?? '',
                'formCode' => $activity->form_code ?? '',
                'planDetails' => $this->decodeJsonObject($activity->form_details ?? null),
                'activityStatus' => $activity->activity_status ?? 'planned',
                'goal' => $activity->goal ?? '',
                'successCriteria' => $activity->success_criteria ?? '',
                'expectedLevel' => (float) ($activity->expected_level ?? 0),
                'actualLevel' => (float) ($activity->actual_level ?? 0),
                'gap' => (float) ($activity->gap ?? 0),
                'updates' => $activityProgress['updates'] ?? [],
                'completion' => $activityProgress['completion'] ?? null,
            ];
        })->values()->all();
    }

    private function progressActivitiesForItems(array $itemIds, bool $includeDrafts): array
    {
        if ($itemIds === []) {
            return [];
        }

        $activities = DB::table('idp_activities')->whereIn('idp_item_id', $itemIds)
            ->orderBy('id')->get(['id', 'idp_item_id', 'activity_name', 'form_code', 'form_details', 'start_date', 'end_date']);
        $activityIds = $activities->pluck('id');
        $updatesQuery = DB::table('idp_activity_updates')->whereIn('activity_id', $activityIds);
        if (! $includeDrafts) {
            $updatesQuery->where('status', 'submitted');
        }
        $updates = $updatesQuery->orderBy('period_start')->orderBy('id')->get();

        $evidences = DB::table('idp_activity_update_evidences')
            ->whereIn('activity_update_id', $updates->pluck('id'))->orderBy('id')->get()->groupBy('activity_update_id');
        $completions = DB::table('idp_item_completion_submissions')
            ->whereIn('idp_item_id', $itemIds)->get()->keyBy('idp_item_id');
        $completionReviewRows = DB::table('idp_item_completion_reviews')
            ->leftJoin('users', 'idp_item_completion_reviews.reviewer_id', '=', 'users.id')
            ->whereIn('idp_item_completion_reviews.completion_submission_id', $completions->pluck('id'))
            ->select(
                'idp_item_completion_reviews.id',
                'idp_item_completion_reviews.completion_submission_id',
                'idp_item_completion_reviews.submission_version',
                'idp_item_completion_reviews.review_step',
                'idp_item_completion_reviews.decision',
                'idp_item_completion_reviews.comment',
                'idp_item_completion_reviews.review_data',
                'idp_item_completion_reviews.decided_at',
                'users.title as reviewer_title',
                'users.name as reviewer_name'
            )
            ->orderByDesc('idp_item_completion_reviews.id')
            ->get();
        $completionReviews = $completionReviewRows
            ->unique('completion_submission_id')
            ->keyBy('completion_submission_id');
        $completionReviewHistory = $completionReviewRows
            ->groupBy('completion_submission_id')
            ->map(fn ($reviews) => $reviews->reverse()->values());
        $ownerIdsByItem = DB::table('idp_items')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->whereIn('idp_items.id', $itemIds)
            ->pluck('idps.user_id', 'idp_items.id');
        $reviewerStepsByOwner = User::query()
            ->whereIn('id', $ownerIdsByItem->values()->unique())
            ->get()
            ->mapWithKeys(fn (User $owner): array => [
                $owner->id => $this->reviewerChainResolver->payloadForUser($owner, 'idp'),
            ]);

        return $activities->groupBy('idp_item_id')->map(function ($itemActivities, $itemId) use ($updates, $evidences, $completions, $completionReviews, $completionReviewHistory, $ownerIdsByItem, $reviewerStepsByOwner): array {
            $completion = $completions[$itemId] ?? null;
            $completionReview = $completion ? ($completionReviews[$completion->id] ?? null) : null;
            $reviewData = $completionReview ? $this->decodeJsonObject($completionReview->review_data ?? null) : [];
            $ownerId = (int) ($ownerIdsByItem[$itemId] ?? 0);
            $currentReviewer = $completion
                ? collect($reviewerStepsByOwner->get($ownerId, []))->firstWhere('step', (int) $completion->current_review_step)
                : null;

            $reviewHistory = $completion
                ? ($completionReviewHistory[$completion->id] ?? collect())
                : collect();

            return $itemActivities->map(function (object $activity) use ($updates, $evidences, $completion, $completionReview, $reviewData, $currentReviewer, $reviewHistory): array {
                return [
                    'id' => (int) $activity->id,
                    'name' => $activity->activity_name ?: 'กิจกรรมพัฒนา',
                    'formCode' => $activity->form_code ?: '',
                    'planDetails' => $this->decodeJsonObject($activity->form_details ?? null),
                    'startDate' => $activity->start_date ?: '',
                    'endDate' => $activity->end_date ?: '',
                    'updates' => $updates->where('activity_id', $activity->id)->map(function (object $update) use ($evidences): array {
                        return [
                            'publicId' => $update->public_id,
                            'topicIndex' => (int) ($update->topic_index ?? 0),
                            'periodStart' => $update->period_start ?: '',
                            'periodEnd' => $update->period_end ?: '',
                            'progressNote' => $update->progress_note ?: '',
                            'status' => $update->status,
                            'submittedAt' => $this->isoUtcTimestamp($update->submitted_at),
                            'evidences' => ($evidences[$update->id] ?? collect())->map(fn (object $evidence): array => [
                                'publicId' => $evidence->public_id,
                                'kind' => $evidence->kind,
                                'name' => $evidence->original_name ?: ($evidence->description ?: 'หลักฐานประกอบ'),
                                'description' => $evidence->description ?: '',
                                'url' => $evidence->kind === 'link'
                                    ? $evidence->url
                                    : route('idp-progress.evidence.show', ['evidence' => $evidence->public_id]),
                            ])->values()->all(),
                        ];
                    })->values()->all(),
                    'completion' => $completion ? [
                        'publicId' => $completion->public_id,
                        'status' => $completion->status,
                        'result' => $completion->result,
                        'currentReviewStep' => $completion->current_review_step ? (int) $completion->current_review_step : null,
                        'currentReviewerName' => $currentReviewer['name'] ?? null,
                        'submissionVersion' => (int) $completion->submission_version,
                        'submittedAt' => $this->isoUtcTimestamp($completion->submitted_at),
                        'approvedAt' => $this->isoUtcTimestamp($completion->approved_at),
                        'updatedAt' => $this->isoUtcTimestamp($completion->updated_at),
                        'reviewHistory' => $reviewHistory->map(fn (object $review): array => [
                            'submissionVersion' => (int) $review->submission_version,
                            'reviewStep' => (int) $review->review_step,
                            'reviewerName' => trim(($review->reviewer_title ?: '').$review->reviewer_name),
                            'decision' => $review->decision,
                            'comment' => $review->comment ?: '',
                            'reviewData' => $this->decodeJsonObject($review->review_data ?? null),
                            'decidedAt' => $this->isoUtcTimestamp($review->decided_at),
                        ])->values()->all(),
                        'review' => $completionReview ? [
                            'reviewerName' => trim(($completionReview->reviewer_title ?: '').$completionReview->reviewer_name),
                            'decision' => $completionReview->decision,
                            'comment' => $completionReview->comment ?: '',
                            'operationStatus' => $reviewData['operationStatus'] ?? null,
                            'operationReason' => $reviewData['operationReason'] ?? '',
                            'achievementStatus' => $reviewData['achievementStatus'] ?? null,
                            'achievementNote' => $reviewData['achievementNote'] ?? '',
                            'decidedAt' => $this->isoUtcTimestamp($completionReview->decided_at),
                        ] : null,
                    ] : null,
                ];
            })->values()->all();
        })->all();
    }

    private function activeRoundId(): ?int
    {
        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id');

        return $roundId ? (int) $roundId : null;
    }

    private function roleKeyFromId(int $roleId): string
    {
        $roleKey = DB::table('roles')->where('id', $roleId)->value('key');

        return $roleKey ? $this->normalizeRoleKey($roleKey) : 'employee';
    }

    private function roleKeyForUser(User $user): string
    {
        $roleKey = $user->relationLoaded('role')
            ? $user->role?->key
            : DB::table('roles')->where('id', $user->role_id)->value('key');

        return $this->normalizeRoleKey($roleKey ?: $this->roleKeyFromId($user->role_id));
    }

    private function rolesPayload()
    {

        return DB::table('roles')
            ->orderBy('id')
            ->get(['id', 'key', 'name_th', 'name_en'])
            ->map(fn (object $role) => [
                'id' => $role->id,
                'key' => $role->key,
                'label' => $role->name_th,
                'labelEn' => $role->name_en,
            ]);
    }

    private function roleIdByKey(string $roleKey): ?int
    {
        return DB::table('roles')->where('key', $roleKey)->value('id');
    }

    private function learningCatalogItems()
    {
        $competencyIdsByCatalog = DB::table('learning_catalog_competency')
            ->select('learning_catalog_id', 'competency_id')
            ->orderBy('competency_id')
            ->get()
            ->groupBy('learning_catalog_id')
            ->map(fn ($items) => $items->pluck('competency_id')->values());
        $formCodesByDeliveryType = Schema::hasColumn('learning_catalog_delivery_types', 'form_code')
            ? DB::table('learning_catalog_delivery_types')->pluck('form_code', 'key')
            : collect(['e_learning' => 'form_10_training', 'in_class' => 'form_10_training']);

        $columns = [
            'learning_catalogs.id',
            'learning_catalogs.code',
            'learning_catalogs.name',
            'learning_catalogs.delivery_type',
            'learning_catalogs.source_type',
            'learning_catalogs.provider',
            'learning_catalogs.cost',
            'learning_catalogs.hours',
            'learning_catalogs.expected_levels',
            'learning_catalogs.description',
            'learning_catalogs.is_active',
            'learning_method_types.key as method_key',
            'learning_method_types.label as method_label',
        ];

        return DB::table('learning_catalogs')
            ->leftJoin('learning_method_types', 'learning_catalogs.method_type_id', '=', 'learning_method_types.id')
            ->select($columns)
            ->orderBy('learning_catalogs.name')
            ->get()
            ->map(fn (object $item) => [
                'id' => $item->id,
                'code' => $item->code ?? '',
                'name' => $item->name,
                'methodKey' => $item->method_key,
                'methodLabel' => $item->method_label,
                'deliveryType' => $item->delivery_type ?? 'e_learning',
                'formCode' => $formCodesByDeliveryType[$item->delivery_type ?? 'e_learning'] ?? 'form_10_training',
                'sourceType' => $item->source_type ?? 'internal',
                'provider' => $item->provider ?? '',
                'cost' => $item->cost,
                'hours' => $item->hours,
                'expectedLevels' => $this->decodeExpectedLevels($item->expected_levels),
                'competencyIds' => $competencyIdsByCatalog[$item->id] ?? [],
                'description' => $item->description ?? '',
                'isActive' => (bool) $item->is_active,
            ]);
    }

    private function decodeExpectedLevels($levels): array
    {
        if (! $levels) {
            return [];
        }

        $decoded = is_string($levels) ? json_decode($levels, true) : $levels;

        return collect(is_array($decoded) ? $decoded : [])
            ->map(fn ($level) => (int) $level)
            ->filter(fn ($level) => $level >= 1 && $level <= 5)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function idpLearningMethods()
    {
        $columns = ['id', 'focus_type', 'title', 'template_file_name', 'is_active'];
        if (Schema::hasColumn('idp_learning_methods', 'form_code')) {
            $columns[] = 'form_code';
        }

        return DB::table('idp_learning_methods')
            ->select($columns)
            ->whereIn('focus_type', ['experiential', 'social'])
            ->orderBy('focus_type')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (object $item) => [
                'id' => $item->id,
                'focusType' => $item->focus_type,
                'title' => $item->title,
                'formCode' => $item->form_code ?? '',
                'templateFileName' => $item->template_file_name ?? '',
                'isActive' => (bool) $item->is_active,
            ]);
    }

    private function idpDeliveryTypeSettings(): array
    {
        $defaults = [
            'e_learning' => [
                'value' => 'e_learning',
                'code' => '',
                'label' => 'การฝึกอบรมออนไลน์ (e-Learning)',
                'formCode' => 'form_9_field_trip',
            ],
            'in_class' => [
                'value' => 'in_class',
                'code' => '',
                'label' => 'การฝึกอบรมในห้องเรียน (In Class Training)',
                'formCode' => 'form_10_training',
            ],
        ];

        if (! Schema::hasTable('learning_catalog_delivery_types')) {
            return array_values($defaults);
        }

        $deliveryColumns = ['key', 'code', 'name_th', 'name_en', 'is_active'];
        if (Schema::hasColumn('learning_catalog_delivery_types', 'form_code')) {
            $deliveryColumns[] = 'form_code';
        }

        $deliveryTypes = DB::table('learning_catalog_delivery_types')
            ->whereIn('key', array_keys($defaults))
            ->get($deliveryColumns)
            ->keyBy('key');

        return collect($defaults)
            ->map(function (array $item, string $deliveryType) use ($deliveryTypes) {
                $row = $deliveryTypes[$deliveryType] ?? null;

                return [
                    ...$item,
                    'code' => $row->code ?? $item['code'],
                    'label' => $row
                        ? trim($row->name_th.' '.($row->name_en ? "({$row->name_en})" : ''))
                        : $item['label'],
                    'formCode' => $row->form_code ?? 'form_10_training',
                    'isActive' => $row ? (bool) $row->is_active : true,
                ];
            })
            ->values()
            ->all();
    }
}
