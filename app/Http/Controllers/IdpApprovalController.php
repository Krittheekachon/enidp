<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IdpItemReviewWorkflow;
use App\Services\NotificationService;
use App\Services\ReviewerChainResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdpApprovalController extends Controller
{
    public function __construct(
        private readonly IdpItemReviewWorkflow $reviewWorkflow,
        private readonly ReviewerChainResolver $reviewerChainResolver,
        private readonly NotificationService $notifications,
    ) {
    }

    public function activityDetail(int $activity): JsonResponse
    {
        $row = DB::table('idp_activities')
            ->join('idp_items', 'idp_activities.idp_item_id', '=', 'idp_items.id')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->where('idp_activities.id', $activity)
            ->select(
                'idp_activities.id',
                'idp_activities.form_code',
                'idp_activities.form_details',
                'idps.user_id'
            )
            ->first();

        abort_unless($row, 404);

        $reviewerId = (int) auth()->id();
        $canView = collect($this->reviewerChainResolver->stepsForUser($row, 'idp'))
            ->contains(fn (array $step): bool => (int) $step['reviewer_id'] === $reviewerId);

        abort_unless($canView, 403);

        $formDetails = $row->form_details;
        if (is_string($formDetails)) {
            $formDetails = json_decode($formDetails, true);
        }

        return response()->json([
            'id' => (int) $row->id,
            'formCode' => (string) ($row->form_code ?? ''),
            'formDetails' => is_array($formDetails) ? $formDetails : [],
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idpItemId' => ['required', 'integer'],
            'comment' => ['nullable', 'string'],
        ]);

        $notification = null;

        DB::transaction(function () use ($validated, &$notification): void {
            $item = $this->reviewableItem((int) $validated['idpItemId']);
            $step = (int) $item->current_review_step;
            $now = now();

            $this->recordDecision(
                $item,
                $step,
                'approved',
                filled($validated['comment'] ?? null)
                    ? trim($validated['comment'])
                    : null,
                $now
            );

            $nextStep = $this->reviewWorkflow->nextStep($item, $step);
            $newStatus = $nextStep
                ? $this->reviewWorkflow->statusForStep($nextStep)
                : 'approved';

            DB::table('idp_items')->where('id', $item->id)->update($nextStep
                ? [
                    'status' => $newStatus,
                    'current_review_step' => $nextStep,
                    'updated_at' => $now,
                ]
                : [
                    'status' => 'approved',
                    'current_review_step' => null,
                    'approved_by' => auth()->id(),
                    'approved_at' => $now,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'reject_comment' => null,
                    'updated_at' => $now,
                ]);

            $this->reviewWorkflow->syncParentStatus((int) $item->idp_id);

            $notification = [
                'employee_id' => (int) $item->user_id,
                'item_id' => (int) $item->id,
                'status' => $newStatus,
                'next_step' => $nextStep ? (int) $nextStep : null,
            ];
        });

        $this->sendApprovalNotifications($notification);

        return back()->with('success', 'อนุมัติแผนสมรรถนะแล้ว');
    }

    public function reject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'idpItemId' => ['required', 'integer'],
            'comment' => ['required', 'string'],
        ]);

        $notification = null;

        DB::transaction(function () use ($validated, &$notification): void {
            $item = $this->reviewableItem((int) $validated['idpItemId']);
            $step = (int) $item->current_review_step;
            $comment = trim($validated['comment']);
            $now = now();

            $this->recordDecision($item, $step, 'rejected', $comment, $now);

            DB::table('idp_items')->where('id', $item->id)->update([
                'status' => 'revision_required',
                'current_review_step' => null,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => auth()->id(),
                'rejected_at' => $now,
                'reject_comment' => $comment,
                'updated_at' => $now,
            ]);

            $this->reviewWorkflow->syncParentStatus((int) $item->idp_id);

            $notification = [
                'employee_id' => (int) $item->user_id,
                'item_id' => (int) $item->id,
                'status' => 'revision_required',
                'reject_comment' => $comment,
            ];
        });

        $this->sendRejectionNotification($notification);

        return back()->with('success', 'ส่งกลับแผนสมรรถนะให้แก้ไขแล้ว');
    }

    private function sendApprovalNotifications(?array $notification): void
    {
        if (! $notification) {
            return;
        }

        $employee = User::find((int) $notification['employee_id']);
        if (! $employee) {
            return;
        }

        $this->notifications->notifyEmployeeIdpStatusUpdate(
            $employee,
            (int) $notification['item_id'],
            (string) $notification['status'],
        );

        if ($notification['next_step']) {
            $this->notifications->notifyIdpReviewerForItem(
                $employee,
                (int) $notification['item_id'],
                (int) $notification['next_step'],
            );
        }
    }

    private function sendRejectionNotification(?array $notification): void
    {
        if (! $notification) {
            return;
        }

        $employee = User::find((int) $notification['employee_id']);
        if (! $employee) {
            return;
        }

        $this->notifications->notifyEmployeeIdpStatusUpdate(
            $employee,
            (int) $notification['item_id'],
            (string) $notification['status'],
            (string) $notification['reject_comment'],
        );
    }

    private function reviewableItem(int $itemId): object
    {
        $roundId = DB::table('assessment_rounds')->where('is_active', true)->orderByDesc('id')->value('id');
        $item = DB::table('idp_items')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->leftJoin('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->leftJoin('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->join('users', 'idps.user_id', '=', 'users.id')
            ->where('idp_items.id', $itemId)
            ->when($roundId, fn ($query) => $query->where('assessments.assessment_round_id', $roundId))
            ->select(
                'idp_items.id',
                'idp_items.idp_id',
                'idp_items.status',
                'idp_items.submission_version',
                'idp_items.current_review_step',
                'users.id as user_id'
            )
            ->lockForUpdate()
            ->first();

        if (! $item) {
            throw ValidationException::withMessages([
                'idpItemId' => 'ไม่พบแผนสมรรถนะ',
            ]);
        }

        $this->reviewWorkflow->assertCurrentReviewer($item, (int) auth()->id());

        return $item;
    }

    private function recordDecision(
        object $item,
        int $step,
        string $decision,
        ?string $comment,
        $decidedAt
    ): void {
        DB::table('idp_item_reviews')->insert([
            'idp_item_id' => $item->id,
            'submission_version' => $item->submission_version,
            'review_step' => $step,
            'reviewer_id' => auth()->id(),
            'decision' => $decision,
            'comment' => $comment,
            'decided_at' => $decidedAt,
            'created_at' => $decidedAt,
            'updated_at' => $decidedAt,
        ]);
    }
}
