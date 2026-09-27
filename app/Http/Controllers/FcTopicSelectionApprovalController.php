<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AssessmentRoundWindow;
use App\Services\NotificationService;
use App\Services\ReviewerChainResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FcTopicSelectionApprovalController extends Controller
{
    public function __construct(
        private AssessmentRoundWindow $assessmentRoundWindow,
        private NotificationService $notifications,
        private ReviewerChainResolver $reviewerChainResolver,
    ) {}

    public function approve(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'selection_id' => ['required', 'integer', 'exists:fc_topic_selections,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $selection = $this->selectionForReviewer($request, (int) $data['selection_id']);

        DB::table('fc_topic_selections')
            ->where('id', $selection->id)
            ->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()->id,
                'review_comment' => trim((string) ($data['comment'] ?? '')) ?: null,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $employee = User::find((int) $selection->user_id);
        if ($employee) {
            $this->notifications->notifyEmployeeFcTopicSelectionStatusUpdate(
                $employee,
                (int) $selection->id,
                'approved',
                trim((string) ($data['comment'] ?? '')),
            );
        }

        return back()->with('success', 'อนุมัติหัวข้อ FC แล้ว');
    }

    public function reject(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'selection_id' => ['required', 'integer', 'exists:fc_topic_selections,id'],
            'comment' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $comment = trim((string) $data['comment']);
        if ($comment === '') {
            throw ValidationException::withMessages([
                'comment' => 'กรุณากรอกเหตุผลก่อนส่งกลับให้เลือกใหม่',
            ]);
        }

        $selection = $this->selectionForReviewer($request, (int) $data['selection_id']);

        DB::table('fc_topic_selections')
            ->where('id', $selection->id)
            ->update([
                'status' => 'revision_required',
                'reviewed_by' => $request->user()->id,
                'review_comment' => $comment,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        $employee = User::find((int) $selection->user_id);
        if ($employee) {
            $this->notifications->notifyEmployeeFcTopicSelectionStatusUpdate(
                $employee,
                (int) $selection->id,
                'revision_required',
                $comment,
            );
        }

        return back()->with('success', 'ส่งหัวข้อ FC กลับให้เลือกใหม่แล้ว');
    }

    private function selectionForReviewer(Request $request, int $selectionId): object
    {
        $selection = DB::table('fc_topic_selections')->where('id', $selectionId)->first();
        $activeRoundId = $this->assessmentRoundWindow->assertSupervisorAssessmentOpen()->id;

        if (! $selection
            || ! $activeRoundId
            || (int) $selection->assessment_round_id !== (int) $activeRoundId
            || $this->reviewerChainResolver->firstReviewerId($selection, 'assessment') !== (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'selection' => 'คุณไม่มีสิทธิ์อนุมัติหัวข้อ FC รายการนี้',
            ]);
        }

        if ($selection->status !== 'submitted') {
            throw ValidationException::withMessages([
                'selection' => 'รายการนี้ไม่ได้อยู่ในสถานะรออนุมัติหัวข้อ FC',
            ]);
        }

        return $selection;
    }
}
