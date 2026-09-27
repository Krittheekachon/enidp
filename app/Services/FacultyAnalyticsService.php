<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FacultyAnalyticsService
{
    private const APPROVED_STATUSES = ['approved', 'dean_approved'];

    private const SELF_ASSESSMENT_ROLE_KEYS = [
        'employee',
        'supervisor',
        'dept_head',
        'division_head',
        'academic_department_head',
        'hr',
    ];

    public function build(?int $assessmentRoundId = null, ?array $visibleUserIds = null): array
    {
        $rounds = DB::table('assessment_rounds')
            ->orderByDesc('is_active')
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->get(['id', 'name', 'year', 'is_active']);
        $round = $assessmentRoundId
            ? $rounds->first(fn (object $item): bool => (int) $item->id === $assessmentRoundId)
            : null;
        $round ??= $rounds->first(fn (object $item): bool => (bool) $item->is_active)
            ?? $rounds->first();

        $employees = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.is_active', true)
            ->whereIn('roles.key', self::SELF_ASSESSMENT_ROLE_KEYS)
            ->when($visibleUserIds !== null, fn ($query) => $query->whereIn('users.id', $visibleUserIds))
            ->orderBy('users.name')
            ->get([
                'users.id',
                'users.title',
                'users.name',
                'users.position',
                'users.position_id',
                'users.department',
                'users.workline',
            ]);

        $empty = $this->emptyPayload($round, $rounds, $employees);
        if (! $round || $employees->isEmpty()) {
            return $empty;
        }

        $employeeIds = $employees->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $employeesById = $employees->keyBy('id');
        $roundAssessments = DB::table('assessments')
            ->leftJoin('competencies', 'assessments.competency_id', '=', 'competencies.id')
            ->leftJoin('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->where('assessment_round_id', $round->id)
            ->whereIn('user_id', $employeeIds)
            ->get([
                'assessments.id',
                'assessments.user_id',
                'assessments.competency_id',
                'assessments.status',
                'competency_types.code as competency_type',
            ]);
        $requiredRoundAssessments = $this->requiredAssessmentRows($roundAssessments, $employees, (int) $round->id);
        $approvedAssessments = $roundAssessments
            ->whereIn('status', self::APPROVED_STATUSES)
            ->values();
        $approvedAssessmentIds = $approvedAssessments->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $assessedUserIds = $requiredRoundAssessments
            ->groupBy('user_id')
            ->filter(fn (Collection $rows): bool => $rows->isNotEmpty()
                && $rows->every(fn (object $assessment): bool => in_array($assessment->status, self::APPROVED_STATUSES, true)))
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->values();

        if ($approvedAssessmentIds === []) {
            return [
                ...$empty,
                'summary' => $this->summary($employees, $assessedUserIds, collect()),
                'worklines' => $this->worklineRows($employees, $assessedUserIds, collect()),
            ];
        }

        $gaps = DB::table('competency_gaps')
            ->join('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->join('competencies', 'competency_gaps.competency_id', '=', 'competencies.id')
            ->join('competency_types', 'competencies.competency_type_id', '=', 'competency_types.id')
            ->whereIn('competency_gaps.assessment_id', $approvedAssessmentIds)
            ->whereIn('competency_gaps.status', self::APPROVED_STATUSES)
            ->where('competency_gaps.gap', '<', 0)
            ->orderBy('competencies.code')
            ->get([
                'competency_gaps.id as gap_id',
                'assessments.user_id',
                'competencies.id as competency_id',
                'competencies.code as competency_code',
                'competencies.name as competency_name',
                'competency_types.code as competency_type',
                'competency_gaps.expected_level',
                'competency_gaps.actual_level',
                'competency_gaps.gap',
                'competency_gaps.requires_idp',
            ]);

        $idpStateByGap = $this->idpStateByGap($gaps);
        $heatmap = $this->heatmapRows($gaps, $employeesById, $idpStateByGap);
        $assessmentGaps = $gaps->whereIn('user_id', $assessedUserIds)->values();

        return [
            'round' => $this->roundPayload($round),
            'rounds' => $rounds->map(fn (object $item): array => $this->roundPayload($item))->all(),
            'summary' => $this->summary(
                $employees,
                $assessedUserIds,
                $assessmentGaps->pluck('user_id')->unique(),
            ),
            'worklines' => $this->worklineRows($employees, $assessedUserIds, $assessmentGaps),
            'assessmentHeatmap' => $this->heatmapRows($assessmentGaps, $employeesById, $idpStateByGap),
            'heatmap' => $heatmap,
            'idpSummary' => $this->idpSummary($gaps, $idpStateByGap),
            'idpByCompetency' => $this->idpRows($heatmap),
            'urgentCompetencies' => $this->urgentRows($heatmap),
        ];
    }

    private function emptyPayload(?object $round, Collection $rounds, Collection $employees): array
    {
        return [
            'round' => $round ? $this->roundPayload($round) : null,
            'rounds' => $rounds->map(fn (object $item): array => $this->roundPayload($item))->all(),
            'summary' => [
                'totalEmployees' => $employees->count(),
                'assessedEmployees' => 0,
                'employeesWithGap' => 0,
                'employeesWithoutGap' => 0,
            ],
            'worklines' => $this->worklineRows($employees, collect(), collect()),
            'assessmentHeatmap' => [],
            'heatmap' => [],
            'idpSummary' => [
                'requiredEmployees' => 0,
                'startedEmployees' => 0,
                'notStartedEmployees' => 0,
                'totalItems' => 0,
                'startedItems' => 0,
                'states' => [],
            ],
            'idpByCompetency' => [],
            'urgentCompetencies' => [],
        ];
    }

    private function roundPayload(object $round): array
    {
        return [
            'id' => (int) $round->id,
            'name' => $round->name,
            'year' => (int) $round->year,
            'isActive' => (bool) $round->is_active,
        ];
    }

    private function summary(Collection $employees, Collection $assessedUserIds, Collection $gapUserIds): array
    {
        return [
            'totalEmployees' => $employees->count(),
            'assessedEmployees' => $assessedUserIds->unique()->count(),
            'employeesWithGap' => $gapUserIds->unique()->count(),
            'employeesWithoutGap' => max($assessedUserIds->unique()->count() - $gapUserIds->unique()->count(), 0),
        ];
    }

    private function idpSummary(Collection $gaps, Collection $idpStateByGap): array
    {
        $requiredGaps = $gaps->filter(fn (object $gap): bool => (bool) $gap->requires_idp);
        $states = $requiredGaps->map(fn (object $gap): string => (string) (
            $idpStateByGap->get((int) $gap->gap_id)['key'] ?? 'not_started'
        ));
        $statesByUser = $requiredGaps
            ->groupBy('user_id')
            ->map(fn (Collection $rows): Collection => $rows->map(fn (object $gap): string => (string) (
                $idpStateByGap->get((int) $gap->gap_id)['key'] ?? 'not_started'
            )));
        $startedEmployees = $statesByUser
            ->filter(fn (Collection $userStates): bool => $userStates->contains(fn (string $state): bool => $state !== 'not_started'))
            ->count();

        return [
            'requiredEmployees' => $statesByUser->count(),
            'startedEmployees' => $startedEmployees,
            'notStartedEmployees' => max($statesByUser->count() - $startedEmployees, 0),
            'totalItems' => $requiredGaps->count(),
            'startedItems' => $states->filter(fn (string $state): bool => $state !== 'not_started')->count(),
            'states' => $states->countBy()->all(),
        ];
    }

    private function requiredAssessmentRows(Collection $assessments, Collection $employees, int $roundId): Collection
    {
        $positionByUser = $employees->mapWithKeys(fn (object $employee): array => [
            (int) $employee->id => $employee->position_id ? (int) $employee->position_id : null,
        ]);
        $positionsUsingFcSelection = DB::table('position_fc_selection_rules')
            ->where('assessment_round_id', $roundId)
            ->where('required_fc_count', '>', 0)
            ->pluck('position_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();
        $selectedFcByUser = DB::table('fc_topic_selections')
            ->join('fc_topic_selection_items', 'fc_topic_selections.id', '=', 'fc_topic_selection_items.fc_topic_selection_id')
            ->where('fc_topic_selections.assessment_round_id', $roundId)
            ->where('fc_topic_selections.status', 'approved')
            ->get(['fc_topic_selections.user_id', 'fc_topic_selection_items.competency_id'])
            ->groupBy('user_id')
            ->map(fn (Collection $rows): Collection => $rows
                ->pluck('competency_id')
                ->map(fn ($id): int => (int) $id)
                ->flip());

        return $assessments->filter(function (object $assessment) use ($positionByUser, $positionsUsingFcSelection, $selectedFcByUser): bool {
            if (! str_starts_with(strtoupper((string) $assessment->competency_type), 'FC')) {
                return true;
            }

            $userId = (int) $assessment->user_id;
            $positionId = $positionByUser->get($userId);
            if (! $positionId || ! $positionsUsingFcSelection->has($positionId)) {
                return true;
            }

            return (bool) $selectedFcByUser->get($userId)?->has((int) $assessment->competency_id);
        })->values();
    }

    private function worklineRows(Collection $employees, Collection $assessedUserIds, Collection $gaps): array
    {
        $assessed = $assessedUserIds->map(fn ($id): int => (int) $id)->flip();
        $gapUserIds = $gaps->pluck('user_id')->map(fn ($id): int => (int) $id)->unique()->flip();

        return collect([
            ['key' => 'academic', 'label' => 'สายวิชาการ'],
            ['key' => 'support', 'label' => 'สายสนับสนุน'],
        ])->map(function (array $scope) use ($employees, $assessed, $gapUserIds, $gaps): array {
            $users = $employees->filter(fn (object $user): bool => $this->scopeKey($user->workline) === $scope['key']);
            $ids = $users->pluck('id')->map(fn ($id): int => (int) $id);
            $scopeGaps = $gaps->filter(fn (object $gap): bool => $ids->contains((int) $gap->user_id));

            return [
                ...$scope,
                'totalEmployees' => $users->count(),
                'assessedEmployees' => $ids->filter(fn (int $id): bool => $assessed->has($id))->count(),
                'employeesWithGap' => $ids->filter(fn (int $id): bool => $gapUserIds->has($id))->count(),
                'topCompetencies' => $this->topGapCompetencies($scopeGaps),
                'organizations' => $this->organizationRows($scope['key'], $users, $assessed, $gapUserIds, $gaps),
                'gapCount' => $scopeGaps->count(),
                'severeGapEmployees' => $scopeGaps
                    ->filter(fn (object $gap): bool => (float) $gap->gap <= -2)
                    ->pluck('user_id')->unique()->count(),
                'averageGap' => $this->averageGap($scopeGaps),
            ];
        })->all();
    }

    private function organizationRows(
        string $scopeKey,
        Collection $users,
        Collection $assessed,
        Collection $gapUserIds,
        Collection $gaps,
    ): array {
        $groups = [];

        foreach ($users as $user) {
            $parts = collect(explode(' > ', (string) $user->department))
                ->map(fn (string $part): string => trim($part))
                ->filter()
                ->take($scopeKey === 'support' ? 3 : 1)
                ->values();

            if ($parts->isEmpty()) {
                $parts = collect([$scopeKey === 'support' ? 'ไม่ระบุฝ่าย' : 'ไม่ระบุภาควิชา']);
            }

            $paths = $scopeKey === 'support'
                ? $parts->map(fn (string $_, int $index): Collection => $parts->take($index + 1))
                : collect([$parts]);

            foreach ($paths as $pathParts) {
                $path = $pathParts->implode(' > ');
                $groups[$path] ??= [
                    'parts' => $pathParts->all(),
                    'users' => collect(),
                ];
                $groups[$path]['users']->put((int) $user->id, $user);
            }
        }

        ksort($groups, SORT_NATURAL);

        return collect($groups)->map(function (array $group, string $path) use ($scopeKey, $assessed, $gapUserIds, $gaps): array {
            $groupUsers = $group['users'];
            $ids = $groupUsers->keys()->map(fn ($id): int => (int) $id);
            $assessedIds = $ids->filter(fn (int $id): bool => $assessed->has($id));
            $failedIds = $assessedIds->filter(fn (int $id): bool => $gapUserIds->has($id));
            $groupGaps = $gaps->filter(fn (object $gap): bool => $ids->contains((int) $gap->user_id));
            $depth = count($group['parts']) - 1;

            return [
                'key' => ($scopeKey === 'support' ? 'support:' : 'academic:').$path,
                'label' => $group['parts'][$depth],
                'path' => $path,
                'depth' => $depth,
                'level' => $scopeKey === 'academic' ? 'department' : ['division', 'work', 'unit'][$depth],
                'levelLabel' => $scopeKey === 'academic' ? 'ภาควิชา' : ['ฝ่าย', 'งาน', 'หน่วย'][$depth],
                'totalEmployees' => $ids->count(),
                'assessedEmployees' => $assessedIds->count(),
                'passedEmployees' => max($assessedIds->count() - $failedIds->count(), 0),
                'failedEmployees' => $failedIds->count(),
                'pendingEmployees' => max($ids->count() - $assessedIds->count(), 0),
                'topCompetencies' => $this->topGapCompetencies($groupGaps),
            ];
        })->values()->all();
    }

    private function topGapCompetencies(Collection $gaps): array
    {
        return $gaps->groupBy('competency_id')
            ->map(function (Collection $rows): array {
                $first = $rows->first();

                return [
                    'competencyId' => (int) $first->competency_id,
                    'code' => $first->competency_code,
                    'name' => $first->competency_name,
                    'type' => $first->competency_type,
                    'peopleCount' => $rows->pluck('user_id')->unique()->count(),
                ];
            })
            ->sort(fn (array $left, array $right): int => $right['peopleCount'] <=> $left['peopleCount']
                ?: strcmp($left['code'], $right['code']))
            ->take(3)
            ->values()
            ->all();
    }

    private function idpStateByGap(Collection $gaps): Collection
    {
        $gapIds = $gaps
            ->filter(fn (object $gap): bool => (bool) $gap->requires_idp)
            ->pluck('gap_id')->map(fn ($id): int => (int) $id)->all();

        if ($gapIds === []) {
            return collect();
        }

        $items = DB::table('idp_items')
            ->whereIn('competency_gap_id', $gapIds)
            ->orderBy('id')
            ->get(['id', 'competency_gap_id', 'status'])
            ->groupBy('competency_gap_id')
            ->map(fn (Collection $rows): object => $rows->last());
        $itemIds = $items->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $completions = $itemIds === [] ? collect() : DB::table('idp_item_completion_submissions')
            ->whereIn('idp_item_id', $itemIds)
            ->get(['idp_item_id', 'status', 'result'])
            ->keyBy('idp_item_id');
        $dueDates = $itemIds === [] ? collect() : DB::table('idp_activities')
            ->whereIn('idp_item_id', $itemIds)
            ->whereNotNull('end_date')
            ->get(['idp_item_id', 'end_date'])
            ->groupBy('idp_item_id')
            ->map(fn (Collection $rows): ?string => $rows->pluck('end_date')->filter()->sort()->last());
        $today = Carbon::today(config('app.timezone', 'Asia/Bangkok'));

        return collect($gapIds)->mapWithKeys(function (int $gapId) use ($items, $completions, $dueDates, $today): array {
            $item = $items->get($gapId);
            if (! $item) {
                return [$gapId => ['key' => 'not_started', 'overdue' => false]];
            }

            $completion = $completions->get($item->id);
            $state = $this->idpState((string) $item->status, $completion);
            $dueDate = $dueDates->get($item->id);
            $waitingForReview = $completion && preg_match('/^review_step_\d+$/', (string) $completion->status) === 1;
            $completed = $completion && $completion->status === 'approved';
            $overdue = $item->status === 'approved'
                && $dueDate
                && ! $waitingForReview
                && ! $completed
                && Carbon::parse($dueDate)->startOfDay()->lt($today);

            return [$gapId => [
                'key' => $state,
                'overdue' => $overdue,
                'dueDate' => $dueDate,
            ]];
        });
    }

    private function idpState(string $itemStatus, ?object $completion): string
    {
        if ($itemStatus === 'revision_required') {
            return 'revision_required';
        }
        if ($itemStatus === 'draft') {
            return 'draft';
        }
        if (preg_match('/^review_step_\d+$/', $itemStatus) === 1) {
            return 'plan_review';
        }
        if ($itemStatus !== 'approved' || ! $completion) {
            return 'developing';
        }
        if (preg_match('/^review_step_\d+$/', (string) $completion->status) === 1) {
            return 'result_review';
        }
        if ($completion->status === 'approved' && $completion->result === 'passed') {
            return 'passed';
        }
        if ($completion->status === 'approved' && $completion->result === 'failed') {
            return 'failed';
        }

        return 'developing';
    }

    private function heatmapRows(Collection $gaps, Collection $employeesById, Collection $idpStateByGap): array
    {
        return $gaps->groupBy('competency_id')->map(function (Collection $rows) use ($employeesById, $idpStateByGap): array {
            $first = $rows->first();
            $cells = collect(['faculty', 'academic', 'support'])->mapWithKeys(function (string $scope) use ($rows, $employeesById, $idpStateByGap): array {
                $scopeRows = $scope === 'faculty'
                    ? $rows
                    : $rows->filter(function (object $gap) use ($scope, $employeesById): bool {
                        $user = $employeesById->get($gap->user_id);

                        return $user && $this->scopeKey($user->workline) === $scope;
                    });

                return [$scope => $this->heatCell($scopeRows, $employeesById, $idpStateByGap)];
            })->all();

            return [
                'competencyId' => (int) $first->competency_id,
                'code' => $first->competency_code,
                'name' => $first->competency_name,
                'type' => $first->competency_type,
                ...$cells,
            ];
        })->sort(function (array $left, array $right): int {
            $people = $right['faculty']['peopleCount'] <=> $left['faculty']['peopleCount'];

            return $people !== 0 ? $people : strcmp($left['code'], $right['code']);
        })->values()->all();
    }

    private function heatCell(Collection $rows, Collection $employeesById, Collection $idpStateByGap): array
    {
        return [
            'peopleCount' => $rows->pluck('user_id')->unique()->count(),
            'averageGap' => $this->averageGap($rows),
            'severeCount' => $rows->filter(fn (object $gap): bool => (float) $gap->gap <= -2)->count(),
            'overdueCount' => $rows->filter(fn (object $gap): bool => (bool) ($idpStateByGap->get((int) $gap->gap_id)['overdue'] ?? false))->count(),
            'people' => $rows->map(function (object $gap) use ($employeesById, $idpStateByGap): array {
                $user = $employeesById->get($gap->user_id);
                $idpState = $idpStateByGap->get((int) $gap->gap_id, ['key' => 'not_started', 'overdue' => false]);

                return [
                    'userId' => (int) $gap->user_id,
                    'name' => trim(($user?->title ?: '').($user?->name ?: '')),
                    'position' => $user?->position ?: '',
                    'organization' => $user?->department ?: '',
                    'workline' => $user?->workline ?: '',
                    'expectedLevel' => $gap->expected_level !== null ? (int) $gap->expected_level : null,
                    'actualLevel' => $gap->actual_level !== null ? (float) $gap->actual_level : null,
                    'gap' => (float) $gap->gap,
                    'idpStatus' => $idpState['key'],
                    'isOverdue' => (bool) $idpState['overdue'],
                ];
            })->sortBy([['gap', 'asc'], ['name', 'asc']])->values()->all(),
        ];
    }

    private function idpRows(array $heatmap): array
    {
        return collect($heatmap)->map(function (array $row): array {
            $states = collect($row['faculty']['people'])
                ->countBy('idpStatus')
                ->all();

            return [
                'competencyId' => $row['competencyId'],
                'code' => $row['code'],
                'name' => $row['name'],
                'needsIdp' => $row['faculty']['peopleCount'],
                'overdueCount' => $row['faculty']['overdueCount'],
                'states' => $states,
            ];
        })->sortByDesc('needsIdp')->values()->all();
    }

    private function urgentRows(array $heatmap): array
    {
        return collect($heatmap)->map(function (array $row): array {
            $cell = $row['faculty'];
            $notStarted = collect($cell['people'])->where('idpStatus', 'not_started')->count();
            $reasons = [];
            if ($cell['severeCount'] > 0) {
                $reasons[] = "Gap ระดับ -2 หรือต่ำกว่า {$cell['severeCount']} คน";
            }
            if ($notStarted > 0) {
                $reasons[] = "ยังไม่เริ่ม IDP {$notStarted} คน";
            }
            if ($cell['overdueCount'] > 0) {
                $reasons[] = "IDP ล่าช้า {$cell['overdueCount']} คน";
            }
            if ($reasons === []) {
                $reasons[] = "มีผู้ต้องพัฒนา {$cell['peopleCount']} คน";
            }

            return [
                'competencyId' => $row['competencyId'],
                'code' => $row['code'],
                'name' => $row['name'],
                'peopleCount' => $cell['peopleCount'],
                'averageGap' => $cell['averageGap'],
                'severeCount' => $cell['severeCount'],
                'notStartedCount' => $notStarted,
                'overdueCount' => $cell['overdueCount'],
                'reasons' => $reasons,
            ];
        })->sort(function (array $left, array $right): int {
            foreach (['peopleCount', 'overdueCount', 'notStartedCount'] as $key) {
                $comparison = $right[$key] <=> $left[$key];
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return strcmp($left['code'], $right['code']);
        })->take(5)->values()->all();
    }

    private function averageGap(Collection $rows): ?float
    {
        return $rows->isEmpty() ? null : round((float) $rows->avg('gap'), 2);
    }

    private function scopeKey(?string $workline): string
    {
        $value = trim((string) $workline);
        if (str_contains($value, 'วิชาการ')) {
            return 'academic';
        }
        if (str_contains($value, 'สนับสนุน')) {
            return 'support';
        }

        return 'other';
    }
}
