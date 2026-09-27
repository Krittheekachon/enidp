<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\IdpItemReviewWorkflow;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdpCompletionReviewController extends Controller
{
    public function __construct(
        private readonly IdpItemReviewWorkflow $reviewWorkflow,
        private readonly NotificationService $notifications,
    ) {}

    public function approve(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'completionPublicId' => ['required', 'uuid'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'operationStatus' => ['required', Rule::in(['as_planned', 'not_as_planned'])],
            'operationReason' => ['nullable', 'string', 'max:5000'],
            'achievementStatus' => ['required', Rule::in(['exceeded', 'met', 'not_met'])],
            'achievementNote' => ['nullable', 'string', 'max:5000'],
        ]);

        if ($data['operationStatus'] === 'not_as_planned' && blank($data['operationReason'] ?? null)) {
            throw ValidationException::withMessages(['operationReason' => 'กรุณาระบุเหตุผลที่ไม่เป็นไปตามแผน']);
        }
        if ($data['achievementStatus'] === 'not_met' && blank($data['achievementNote'] ?? null)) {
            throw ValidationException::withMessages(['achievementNote' => 'กรุณาระบุสิ่งที่ควรพัฒนาต่อ']);
        }

        $approvedCompletion = null;
        DB::transaction(function () use ($data, &$approvedCompletion): void {
            $completion = $this->reviewableCompletion($data['completionPublicId']);
            $step = (int) $completion->current_review_step;
            $now = now();
            $this->recordDecision($completion, $step, 'approved', $data, $now);

            DB::table('idp_item_completion_submissions')->where('id', $completion->id)->update([
                'status' => 'approved',
                'result' => $data['achievementStatus'] === 'not_met' ? 'failed' : 'passed',
                'current_review_step' => null,
                'approved_at' => $now,
                'updated_at' => $now,
            ]);

            $activityIds = DB::table('idp_activities')->where('idp_item_id', $completion->idp_item_id)->pluck('id');
            DB::table('idp_activities')->whereIn('id', $activityIds)->update([
                'status' => 'completed',
                'result' => $data['achievementStatus'] === 'not_met' ? 'incomplete' : 'completed',
                'updated_at' => $now,
            ]);

            if ($data['achievementStatus'] === 'not_met') {
                DB::table('idp_items')->insert([
                    'idp_id' => $completion->idp_id,
                    'competency_gap_id' => $completion->competency_gap_id,
                    'behavior_key' => $completion->behavior_key,
                    'behavior_description' => $completion->behavior_description,
                    'target_level' => $completion->target_level,
                    'status' => 'draft',
                    'submission_version' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->reviewWorkflow->syncParentStatus((int) $completion->idp_id);
            }

            $approvedCompletion = $completion;
        });

        if ($approvedCompletion) {
            $employee = User::find((int) $approvedCompletion->user_id);
            if ($employee) {
                $this->notifications->notifyEmployeeIdpProgressApproved(
                    $employee,
                    (int) $approvedCompletion->idp_item_id,
                    $data['achievementStatus'],
                    trim($data['comment'] ?? ''),
                );
            }
        }

        return back()->with('success', 'อนุมัติผลการพัฒนาสมรรถนะแล้ว');
    }

    public function reject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'completionPublicId' => ['required', 'uuid'],
            'comment' => ['required', 'string', 'max:5000'],
            'operationStatus' => ['nullable', Rule::in(['as_planned', 'not_as_planned'])],
            'operationReason' => ['nullable', 'string', 'max:5000'],
            'achievementStatus' => ['nullable', Rule::in(['exceeded', 'met', 'not_met'])],
            'achievementNote' => ['nullable', 'string', 'max:5000'],
        ], ['comment.required' => 'กรุณาระบุเหตุผลที่ส่งกลับ']);

        $returnedCompletion = null;
        DB::transaction(function () use ($data, &$returnedCompletion): void {
            $completion = $this->reviewableCompletion($data['completionPublicId']);
            $now = now();
            $this->recordDecision($completion, (int) $completion->current_review_step, 'rejected', $data, $now);
            DB::table('idp_item_completion_submissions')->where('id', $completion->id)->update([
                'status' => 'revision_required',
                'result' => null,
                'current_review_step' => null,
                'updated_at' => $now,
            ]);

            $returnedCompletion = $completion;
        });

        if ($returnedCompletion) {
            $employee = User::find((int) $returnedCompletion->user_id);
            if ($employee) {
                $this->notifications->notifyEmployeeIdpProgressReturned(
                    $employee,
                    (int) $returnedCompletion->idp_item_id,
                    trim($data['comment']),
                );
            }
        }

        return back()->with('success', 'ส่งผลการพัฒนากลับให้แก้ไขแล้ว');
    }

    private function reviewableCompletion(string $publicId): object
    {
        $roundId = DB::table('assessment_rounds')->where('is_active', true)->orderByDesc('id')->value('id');
        $completion = DB::table('idp_item_completion_submissions')
            ->join('idp_items', 'idp_item_completion_submissions.idp_item_id', '=', 'idp_items.id')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->leftJoin('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->leftJoin('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->where('idp_item_completion_submissions.public_id', $publicId)
            ->when($roundId, fn ($query) => $query->where('assessments.assessment_round_id', $roundId))
            ->select(
                'idp_item_completion_submissions.id',
                'idp_item_completion_submissions.idp_item_id',
                'idp_item_completion_submissions.status',
                'idp_item_completion_submissions.submission_version',
                'idp_item_completion_submissions.current_review_step',
                'idp_items.idp_id',
                'idp_items.competency_gap_id',
                'idp_items.behavior_key',
                'idp_items.behavior_description',
                'idp_items.target_level',
                'idps.user_id'
            )
            ->lockForUpdate()
            ->first();
        if (! $completion) {
            throw ValidationException::withMessages(['completionPublicId' => 'ไม่พบรายการปิดผลการพัฒนา']);
        }

        $step = (int) ($completion->current_review_step ?? 0);
        $firstStep = $this->reviewWorkflow->firstStep($completion);
        if ($step !== $firstStep
            || $completion->status !== $this->reviewWorkflow->statusForStep($step)
            || $this->reviewWorkflow->reviewerIdForStep($completion, $step) !== (int) auth()->id()) {
            throw ValidationException::withMessages([
                'completionPublicId' => 'คุณไม่มีสิทธิ์ตรวจรายการนี้ หรือรายการไม่ได้อยู่ในลำดับของคุณ',
            ]);
        }

        return $completion;
    }

    private function recordDecision(object $completion, int $step, string $decision, array $data, $now): void
    {
        $hasReviewData = collect([
            $data['operationStatus'] ?? null,
            $data['operationReason'] ?? null,
            $data['achievementStatus'] ?? null,
            $data['achievementNote'] ?? null,
        ])->contains(fn (mixed $value): bool => filled($value));
        $reviewData = $hasReviewData ? [
            'operationStatus' => $data['operationStatus'] ?? null,
            'operationReason' => $data['operationReason'] ?? null,
            'achievementStatus' => $data['achievementStatus'] ?? null,
            'achievementNote' => $data['achievementNote'] ?? null,
        ] : null;

        DB::table('idp_item_completion_reviews')->insert([
            'completion_submission_id' => $completion->id,
            'submission_version' => $completion->submission_version,
            'review_step' => $step,
            'reviewer_id' => auth()->id(),
            'decision' => $decision,
            'comment' => filled($data['comment'] ?? null) ? trim($data['comment']) : null,
            'review_data' => $reviewData ? json_encode($reviewData, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'decided_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
