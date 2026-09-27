<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpItemReviewWorkflow
{
    private ReviewerChainResolver $reviewerChainResolver;

    public function __construct(?ReviewerChainResolver $reviewerChainResolver = null)
    {
        $this->reviewerChainResolver = $reviewerChainResolver ?? new ReviewerChainResolver();
    }

    public function configuredSteps(object $owner): Collection
    {
        return collect($this->reviewerChainResolver->stepsForUser($owner, 'idp'))
            ->pluck('step')
            ->map(fn ($step): int => (int) $step)
            ->filter(fn (int $step): bool => $step > 0)
            ->values();
    }

    public function firstStep(object $owner): int
    {
        $step = $this->configuredSteps($owner)->first();

        if (! $step) {
            throw ValidationException::withMessages([
                'item' => 'ยังไม่ได้กำหนดผู้อนุมัติแผน IDP',
            ]);
        }

        return (int) $step;
    }

    public function nextStep(object $owner, int $currentStep): ?int
    {
        $step = $this->configuredSteps($owner)
            ->first(fn (int $step): bool => $step > $currentStep);

        return $step ? (int) $step : null;
    }

    public function reviewerIdForStep(object $owner, int $step): ?int
    {
        $match = collect($this->reviewerChainResolver->stepsForUser($owner, 'idp'))
            ->first(fn (array $item): bool => (int) $item['step'] === $step);

        return $match ? (int) $match['reviewer_id'] : null;
    }

    public function statusForStep(int $step): string
    {
        return 'review_step_'.$step;
    }

    public function isUnderReview(string $status): bool
    {
        return (bool) preg_match('/^review_step_\d+$/', $status);
    }

    public function assertCurrentReviewer(object $item, int $reviewerId): void
    {
        $step = (int) ($item->current_review_step ?? 0);

        if ($step < 1
            || $item->status !== $this->statusForStep($step)
            || $this->reviewerIdForStep($item, $step) !== $reviewerId) {
            throw ValidationException::withMessages([
                'idpItemId' => 'คุณไม่มีสิทธิ์ตรวจแผนสมรรถนะนี้ หรือแผนไม่ได้อยู่ในขั้นตอนของคุณ',
            ]);
        }
    }

    public function syncParentStatus(int $idpId): void
    {
        $statuses = DB::table('idp_items')->where('idp_id', $idpId)->pluck('status');
        $underReview = $statuses->contains(
            fn (string $status): bool => $this->isUnderReview($status)
        );

        $status = match (true) {
            $statuses->isNotEmpty()
                && $statuses->every(fn (string $status): bool => $status === 'approved') => 'approved',
            $underReview => 'partially_submitted',
            $statuses->contains('revision_required') => 'revision_required',
            $statuses->contains('approved') => 'in_progress',
            default => 'draft',
        };

        DB::table('idps')->where('id', $idpId)->update([
            'status' => $status,
            'submitted_at' => $underReview ? now() : null,
            'updated_at' => now(),
        ]);
    }
}
