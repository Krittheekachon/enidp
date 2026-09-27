<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Models\User;
use App\Services\AssessmentRoundWindow;
use App\Services\ExpectedLevelResolver;
use App\Services\NotificationService;
use App\Services\ReviewerChainResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AssessmentController extends Controller
{
    public function __construct(
        private ExpectedLevelResolver $expectedLevelResolver,
        private NotificationService $notifications,
        private ReviewerChainResolver $reviewerChainResolver,
        private AssessmentRoundWindow $assessmentRoundWindow,
    )
    {
    }

    public function draft(Request $request)
    {
        $data = $this->validatedAssessmentPayload($request);
        $this->assessmentRoundWindow->assertSelfAssessmentOpen();

        $this->assertCanSelfAssess($request->user());
        $this->assertFcTopicsApprovedForAssessment($request->user(), (int) $data['competency_id']);

        $savedAt = null;
        $roundId = $this->activeAssessmentRoundId();
        DB::transaction(function () use ($request, $data, &$savedAt): void {
            $savedAt = $this->persistSelfAssessment($request, $data, false);
        });

        $status = DB::table('assessments')
            ->leftJoin('competency_gaps', function ($join) {
                $join->on('competency_gaps.assessment_id', '=', 'assessments.id')
                    ->on('competency_gaps.competency_id', '=', 'assessments.competency_id');
            })
            ->where('assessments.user_id', $request->user()->id)
            ->where('assessments.competency_id', $data['competency_id'])
            ->where('assessments.assessment_round_id', $roundId)
            ->value(DB::raw('COALESCE(competency_gaps.status, assessments.status)'));

        return response()->json([
            'status' => $status ?: 'draft',
            'lastDraftSavedAt' => $savedAt?->toISOString(),
            'message' => 'บันทึกฉบับร่างแล้ว',
        ]);
    }

    public function save(Request $request)
    {
        $data = $this->validatedAssessmentPayload($request);
        $this->assessmentRoundWindow->assertSelfAssessmentOpen();

        $this->assertCanSelfAssess($request->user());
        $this->assertFcTopicsApprovedForAssessment($request->user(), (int) $data['competency_id']);

        DB::transaction(function () use ($request, $data): void {
            $this->persistSelfAssessment($request, $data, true);
        });

        $this->notifications->notifyFirstReviewerOnSubmit(
            $request->user(),
            $this->competencyName((int) $data['competency_id']),
        );

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'บันทึกการประเมินเรียบร้อยแล้ว',
        ]);
    }

    public function load(Request $request)
    {
        $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
        ]);

        $roundId = $this->activeAssessmentRoundId();
        $assessment = Assessment::where('user_id', auth()->id())
            ->where('competency_id', $request->query('competency_id'))
            ->where('assessment_round_id', $roundId)
            ->first();

        if (! $assessment) {
            return response()->json([
                'checked' => [],
                'note' => '',
                'score' => 0,
                'status' => 'draft',
                'gapStatus' => null,
                'lastDraftSavedAt' => null,
                'locked' => false,
                'reject_comment' => '',
                'reject_reviewer_name' => '',
            ]);
        }

        $indicators = DB::table('assessment_indicator_results')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $assessment->competency_id)
            ->where('is_checked', true)
            ->pluck('indicator_key')
            ->mapWithKeys(fn ($key) => [$key => true]);

        $gap = DB::table('competency_gaps')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $assessment->competency_id)
            ->first();
        $gapStatus = $gap?->status;
        $status = $gapStatus ?? $assessment->status ?? 'draft';
        $rejectReviewer = $gap?->rejected_by
            ? User::query()->find($gap->rejected_by)
            : null;

        return response()->json([
            'checked' => $indicators,
            'note' => $assessment->note ?? '',
            'score' => $assessment->score ?? 0,
            'status' => $status,
            'gapStatus' => $gapStatus,
            'lastDraftSavedAt' => $this->isoTimestamp($assessment->last_draft_saved_at),
            'locked' => ! in_array($status, ['draft', 'revision_required'], true),
            'reject_comment' => $gap?->reject_comment ?? '',
            'reject_reviewer_name' => $rejectReviewer
                ? trim(($rejectReviewer->title ?: '').($rejectReviewer->name ?: ''))
                : '',
        ]);
    }

    public function approve(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'comment' => ['nullable', 'string'],
        ]);
        $this->assessmentRoundWindow->assertSupervisorAssessmentOpen();
        $comment = trim((string) ($data['comment'] ?? ''));

        $decision = $this->decisionContextForUser($request->user(), (int) $data['user_id']);
        $employee = User::findOrFail((int) $data['user_id']);
        $competencyName = $this->competencyName((int) ($data['competency_id'] ?? 0));

        DB::transaction(function () use ($data, $decision, $request, $comment, $employee): void {
            $assessmentIds = Assessment::where('user_id', $data['user_id'])
                ->where('competency_id', $data['competency_id'])
                ->where('assessment_round_id', $this->activeAssessmentRoundId())
                ->pluck('id');
            $reviewerScoreId = $this->upsertReviewerScore(
                $assessmentIds,
                (int) $data['competency_id'],
                (int) $request->user()->id,
                $decision['review_step'],
                $comment,
                'approved'
            );

            $this->ensureCompetencyGapsForAssessments(
                $assessmentIds,
                $employee,
                (int) $data['competency_id'],
                $decision['expected_status']
            );

            Assessment::whereIn('id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => $decision['approved_status'],
                    $decision['submitted_at_column'] => now(),
                    'updated_at' => now(),
                ]);

            DB::table('competency_gaps')
                ->whereIn('assessment_id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => $decision['approved_status'],
                    'supervisor_2_score_id' => $reviewerScoreId,
                    'rejected_by' => null,
                    'reject_comment' => null,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        $this->notifications->notifyEmployeeStatusUpdate($employee, $decision['approved_status']);

        if ($decision['approved_status'] !== 'approved') {
            $this->notifications->notifyNextReviewerForAssessment(
                $employee,
                $competencyName,
                $decision['approved_status'],
            );
        }

        return back();
    }

    public function reject(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'comment' => ['required', 'string', 'min:1'],
        ]);
        $this->assessmentRoundWindow->assertSupervisorAssessmentOpen();
        $comment = trim((string) $data['comment']);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'กรุณากรอก Comment ก่อนส่งกลับแก้ไข',
            ]);
        }

        $decision = $this->decisionContextForUser($request->user(), (int) $data['user_id']);
        $employee = User::findOrFail((int) $data['user_id']);

        DB::transaction(function () use ($data, $decision, $request, $comment): void {
            $assessmentIds = Assessment::where('user_id', $data['user_id'])
                ->where('competency_id', $data['competency_id'])
                ->where('assessment_round_id', $this->activeAssessmentRoundId())
                ->pluck('id');
            $reviewerScoreId = $this->upsertReviewerScore(
                $assessmentIds,
                (int) $data['competency_id'],
                (int) $request->user()->id,
                $decision['review_step'],
                $comment,
                'rejected'
            );

            Assessment::whereIn('id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => 'revision_required',
                    'updated_at' => now(),
                ]);

            DB::table('competency_gaps')
                ->whereIn('assessment_id', $assessmentIds)
                ->where('competency_id', $data['competency_id'])
                ->where('status', $decision['expected_status'])
                ->update([
                    'status' => 'revision_required',
                    'supervisor_2_score_id' => $reviewerScoreId,
                    'rejected_by' => $request->user()->id,
                    'reject_comment' => $comment,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        $this->notifications->notifyEmployeeStatusUpdate($employee, 'revision_required', $comment);

        return back();
    }

    private function assertCanSelfAssess($user): void
    {
        $roleKey = $this->normalizeRoleKey(
            $user->relationLoaded('role')
                ? ($user->role?->key ?: '')
                : (DB::table('roles')->where('id', $user->role_id)->value('key') ?: '')
        );

        if (in_array($roleKey, ['admin', 'dean'], true)) {
            return;
        }

        $hasAssignedEvaluator = $this->reviewerChainResolver->stepsForUser($user) !== [];

        if (! $hasAssignedEvaluator) {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดผู้ประเมินก่อน',
            ]);
        }
    }

    private function assertFcTopicsApprovedForAssessment($user, int $competencyId): void
    {
        $positionId = (int) ($user->position_id ?? 0);
        $roundId = $this->activeAssessmentRoundId();
        if ($positionId <= 0 || ! Schema::hasTable('position_fc_selection_rules')) {
            return;
        }

        $requiredCount = (int) DB::table('position_fc_selection_rules')
            ->where('position_id', $positionId)
            ->where('assessment_round_id', $roundId)
            ->value('required_fc_count');

        if ($requiredCount <= 0) {
            return;
        }

        $selection = Schema::hasTable('fc_topic_selections')
            ? DB::table('fc_topic_selections')
                ->where('user_id', $user->id)
                ->where('position_id', $positionId)
                ->where('assessment_round_id', $roundId)
                ->first()
            : null;

        if (! $selection || $selection->status !== 'approved') {
            throw ValidationException::withMessages([
                'assessment' => 'ยังไม่สามารถประเมินได้ กรุณาเลือกหัวข้อ FC และรอหัวหน้า 1 อนุมัติก่อน',
            ]);
        }

        $isFcCompetency = DB::table('competencies')
            ->join('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('competencies.id', $competencyId)
            ->whereIn('competency_types.code', ['FC', 'FC1', 'FC2'])
            ->exists();

        if (! $isFcCompetency) {
            return;
        }

        $isSelectedFc = Schema::hasTable('fc_topic_selection_items') && DB::table('fc_topic_selection_items')
            ->where('fc_topic_selection_id', $selection->id)
            ->where('competency_id', $competencyId)
            ->exists();

        if (! $isSelectedFc) {
            throw ValidationException::withMessages([
                'assessment' => 'ประเมินได้เฉพาะหัวข้อ FC ที่หัวหน้า 1 อนุมัติแล้วเท่านั้น',
            ]);
        }
    }

    private function validatedAssessmentPayload(Request $request): array
    {
        return $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'checked_indicators' => ['present', 'array'],
            'note' => ['nullable', 'string', 'max:2000'],
            'score' => ['required', 'numeric'],
        ]);
    }

    private function persistSelfAssessment(Request $request, array $data, bool $submit): Carbon
    {
        $userId = auth()->id();
        $competencyId = (int) $data['competency_id'];
        $roundId = $this->activeAssessmentRoundId();
        $checkedIndicators = collect($data['checked_indicators'])
            ->filter()
            ->filter(fn ($checked, string $key): bool => str_starts_with($key, $competencyId.':'))
            ->all();
        $existingAssessment = Assessment::where('user_id', $userId)
            ->where('competency_id', $competencyId)
            ->where('assessment_round_id', $roundId)
            ->first();
        $existingGapStatus = $existingAssessment
            ? DB::table('competency_gaps')
                ->where('assessment_id', $existingAssessment->id)
                ->where('competency_id', $competencyId)
                ->value('status')
            : null;
        $existingStatus = $existingGapStatus ?? $existingAssessment?->status;

        if ($existingAssessment && ! in_array($existingStatus, ['draft', 'revision_required'], true)) {
            throw ValidationException::withMessages([
                'assessment' => 'ผลการประเมินนี้ถูกส่งให้หัวหน้างานแล้ว ไม่สามารถแก้ไขได้จนกว่าจะถูกส่งกลับมาแก้ไข',
            ]);
        }

        $savedAt = now();
        $submittedStatus = $submit
            ? $this->initialSubmittedStatusForUser($request->user())
            : ($existingStatus === 'revision_required' ? 'revision_required' : 'draft');
        $assessmentAttributes = [
            'user_id' => $userId,
            'competency_id' => $competencyId,
            'assessment_round_id' => $roundId,
        ];
        $assessmentValues = [
            'score' => $data['score'],
            'note' => $data['note'] ?? '',
            'status' => $submittedStatus,
            'last_draft_saved_at' => $savedAt,
            'self_submitted_at' => $submit ? $savedAt : $existingAssessment?->self_submitted_at,
        ];

        $assessment = Assessment::updateOrCreate($assessmentAttributes, $assessmentValues);

        DB::table('assessment_indicator_results')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $competencyId)
            ->delete();

        foreach (array_keys($checkedIndicators) as $key) {
            DB::table('assessment_indicator_results')->insert([
                'assessment_id' => $assessment->id,
                'competency_id' => $competencyId,
                'indicator_key' => $key,
                'is_checked' => true,
                'checked_by' => $userId,
                'checked_at' => $savedAt,
                'created_at' => $savedAt,
                'updated_at' => $savedAt,
            ]);
        }

        $expectedLevel = $this->expectedLevelResolver->forUserCompetency(
            $request->user(),
            $competencyId
        );
        $actualLevel = round((float) $data['score'], 2);
        $gap = $expectedLevel === null ? null : round($actualLevel - $expectedLevel, 2);

        DB::table('competency_gaps')->updateOrInsert(
            [
                'assessment_id' => $assessment->id,
                'competency_id' => $competencyId,
            ],
            [
                'expected_level' => $expectedLevel,
                'actual_level' => $actualLevel,
                'gap' => $gap,
                'requires_idp' => $gap !== null && $gap < 0,
                'status' => $submittedStatus,
                'updated_at' => $savedAt,
            ]
        );

        return $savedAt;
    }

    private function ensureCompetencyGapsForAssessments($assessmentIds, User $employee, int $competencyId, string $status): void
    {
        $now = now();
        $expectedLevel = $this->expectedLevelResolver->forUserCompetency($employee, $competencyId);

        Assessment::query()
            ->whereIn('id', $assessmentIds)
            ->where('competency_id', $competencyId)
            ->where('status', $status)
            ->get(['id', 'score'])
            ->each(function (Assessment $assessment) use ($competencyId, $expectedLevel, $status, $now): void {
                $actualLevel = round((float) ($assessment->score ?? 0), 2);
                $gap = $expectedLevel === null ? null : round($actualLevel - $expectedLevel, 2);
                $attributes = [
                    'assessment_id' => $assessment->id,
                    'competency_id' => $competencyId,
                ];
                $values = [
                    'expected_level' => $expectedLevel,
                    'actual_level' => $actualLevel,
                    'gap' => $gap,
                    'requires_idp' => $gap !== null && $gap < 0,
                    'status' => $status,
                    'updated_at' => $now,
                ];

                if (DB::table('competency_gaps')->where($attributes)->exists()) {
                    DB::table('competency_gaps')->where($attributes)->update($values);

                    return;
                }

                DB::table('competency_gaps')->insert($attributes + $values + [
                    'created_at' => $now,
                ]);
            });
    }

    private function activeAssessmentRoundId(): int
    {
        return (int) $this->assessmentRoundWindow->activeRound()->id;
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }

    private function competencyName(int $competencyId): string
    {
        if ($competencyId <= 0) {
            return 'หลายสมรรถนะ';
        }

        return DB::table('competencies')
            ->where('id', $competencyId)
            ->value('name') ?? 'ไม่ระบุสมรรถนะ';
    }

    private function decisionContextForUser($reviewer, int $userId): array
    {
        $target = DB::table('users')->where('id', $userId)->first();

        if (! $target) {
            throw ValidationException::withMessages([
                'assessment' => 'คุณไม่มีสิทธิ์อนุมัติผลการประเมินของบุคลากรคนนี้',
            ]);
        }

        $reviewStep = $this->reviewStepForReviewer($target, (int) $reviewer->id);

        if (! $reviewStep) {
            throw ValidationException::withMessages([
                'assessment' => 'คุณไม่มีสิทธิ์อนุมัติผลการประเมินของบุคลากรคนนี้',
            ]);
        }

        return [
            'expected_status' => $this->reviewerChainResolver->pendingStatusForStep($reviewStep),
            'approved_status' => $this->nextStatusAfterStep($target, $reviewStep),
            'submitted_at_column' => $this->submittedAtColumnForStep($reviewStep),
            'review_step' => $reviewStep,
        ];
    }

    private function upsertReviewerScore($assessmentIds, int $competencyId, int $reviewerId, int $reviewStep, string $comment, string $status): ?int
    {
        $scoreId = null;
        $now = now();

        foreach ($assessmentIds as $assessmentId) {
            DB::table('scores')->updateOrInsert(
                [
                    'assessment_id' => $assessmentId,
                    'competency_id' => $competencyId,
                    'assessor_id' => $reviewerId,
                ],
                [
                    'assessor_role' => 'supervisor_'.$reviewStep,
                    'comment' => $comment === '' ? null : $comment,
                    'status' => $status,
                    'submitted_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $scoreId ??= (int) DB::table('scores')
                ->where('assessment_id', $assessmentId)
                ->where('competency_id', $competencyId)
                ->where('assessor_id', $reviewerId)
                ->value('id');
        }

        return $scoreId;
    }

    private function initialSubmittedStatusForUser($user): string
    {
        $steps = $this->reviewerChainResolver->stepsForUser($user);

        if ($steps !== []) {
            return $this->reviewerChainResolver->pendingStatusForStep((int) $steps[0]['step']);
        }

        throw ValidationException::withMessages([
            'assessment' => 'ยังไม่สามารถประเมินตนเองได้ กรุณาให้ Admin กำหนดผู้ประเมินก่อน',
        ]);
    }

    private function reviewStepForReviewer($target, int $reviewerId): ?int
    {
        return $this->reviewerChainResolver->stepForReviewer($target, $reviewerId);
    }

    private function nextStatusAfterStep($target, int $currentStep): string
    {
        return $this->reviewerChainResolver->nextStatusAfterStep($target, $currentStep);
    }

    private function submittedAtColumnForStep(int $step): string
    {
        return $this->reviewerChainResolver->submittedAtColumnForStep($step);
    }

    private function isoTimestamp($value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof Carbon
            ? $value->toISOString()
            : Carbon::parse($value)->toISOString();
    }
}
