<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReviewerChainResolver
{
    public function stepsForUser(object $user, string $chainType = 'assessment'): array
    {
        $userId = (int) ($user->user_id ?? $user->id ?? 0);

        if ($userId > 0 && Schema::hasTable('user_reviewer_steps')) {
            $rows = $this->rowsForUser($userId, $chainType);

            if ($rows->isNotEmpty()) {
                return $rows
                    ->map(fn (object $row): array => [
                        'step' => (int) $row->step_order,
                        'reviewer_id' => (int) $row->reviewer_id,
                    ])
                    ->filter(fn (array $step): bool => $step['step'] > 0 && $step['reviewer_id'] > 0)
                    ->values()
                    ->all();
            }
        }

        return [];
    }

    public function payloadForUser(User $user, string $chainType = 'assessment'): array
    {
        $steps = $this->stepsForUser($user, $chainType);

        if ($steps === []) {
            return [];
        }

        $reviewers = User::query()
            ->with('role')
            ->whereIn('id', collect($steps)->pluck('reviewer_id'))
            ->get()
            ->keyBy('id');

        return collect($steps)
            ->map(function (array $step) use ($reviewers, $chainType): ?array {
                $reviewer = $reviewers->get($step['reviewer_id']);

                if (! $reviewer) {
                    return null;
                }

                return [
                    'id' => (int) $reviewer->id,
                    'chainType' => $chainType,
                    'step' => (int) $step['step'],
                    'label' => 'ผู้ประเมินลำดับ '.(int) $step['step'],
                    'name' => trim(($reviewer->title ?: '').$reviewer->name),
                    'position' => $reviewer->position ?: '',
                    'role' => $this->normalizeRoleKey((string) ($reviewer->role?->key ?? '')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function firstReviewerId(object $user, string $chainType = 'assessment'): ?int
    {
        $firstStep = $this->stepsForUser($user, $chainType)[0] ?? null;

        return $firstStep ? (int) $firstStep['reviewer_id'] : null;
    }

    public function stepForReviewer(object $user, int $reviewerId, string $chainType = 'assessment'): ?int
    {
        foreach ($this->stepsForUser($user, $chainType) as $step) {
            if ((int) $step['reviewer_id'] === $reviewerId) {
                return (int) $step['step'];
            }
        }

        return null;
    }

    public function userIdsForReviewer(object $reviewer, string $chainType = 'assessment'): array
    {
        $reviewerId = (int) ($reviewer->reviewer_id ?? $reviewer->id ?? 0);

        if ($reviewerId <= 0 || ! Schema::hasTable('user_reviewer_steps')) {
            return [];
        }

        $query = DB::table('user_reviewer_steps')
            ->where('reviewer_id', $reviewerId);

        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $query->where('chain_type', $chainType);
        }

        return $query
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function pendingStatusForStep(int $step): string
    {
        return match ($step) {
            1 => 'self_submitted',
            2 => 'unit_evaluated',
            3 => 'dept_evaluated',
            default => 'review_step_'.$step,
        };
    }

    public function nextStatusAfterStep(object $user, int $currentStep, string $chainType = 'assessment'): string
    {
        foreach ($this->stepsForUser($user, $chainType) as $step) {
            if ((int) $step['step'] > $currentStep) {
                return $this->pendingStatusForStep((int) $step['step']);
            }
        }

        return 'approved';
    }

    public function submittedAtColumnForStep(int $step): string
    {
        return match ($step) {
            1 => 'supervisor_1_submitted_at',
            2 => 'supervisor_2_submitted_at',
            3 => 'dean_approved_at',
            default => 'updated_at',
        };
    }

    private function rowsForUser(int $userId, string $chainType)
    {
        $query = DB::table('user_reviewer_steps')
            ->where('user_id', $userId);

        if (Schema::hasColumn('user_reviewer_steps', 'chain_type')) {
            $query->where('chain_type', $chainType);
        }

        return $query
            ->orderBy('step_order')
            ->get(['step_order', 'reviewer_id']);
    }

    private function normalizeRoleKey(string $roleKey): string
    {
        return match ($roleKey) {
            'manager' => 'dean',
            'manager_dept' => 'dept_head',
            default => $roleKey,
        };
    }
}
