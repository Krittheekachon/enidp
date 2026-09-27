<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\IdpItemReviewWorkflow;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdpActivityUpdateController extends Controller
{
    public function __construct(
        private readonly IdpItemReviewWorkflow $reviewWorkflow,
        private readonly NotificationService $notifications,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'activityId' => ['required', 'integer'],
            'updatePublicId' => ['nullable', 'uuid'],
            'action' => ['required', Rule::in(['submit'])],
            'topicIndex' => ['required', 'integer', 'min:0'],
            'periodStart' => ['nullable', 'date'],
            'periodEnd' => ['nullable', 'date', 'after_or_equal:periodStart'],
            'progressNote' => ['nullable', 'string', 'max:10000'],
            'evidenceLinks' => ['nullable', 'array', 'max:10'],
            'evidenceLinks.*.url' => ['required_with:evidenceLinks', 'url:http,https', 'max:2048'],
            'evidenceLinks.*.description' => ['nullable', 'string', 'max:255'],
            'evidenceFiles' => ['nullable', 'array', 'max:10'],
            'evidenceFiles.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt'],
        ], [
            'periodEnd.after_or_equal' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
            'evidenceFiles.*.max' => 'ไฟล์หลักฐานแต่ละไฟล์ต้องมีขนาดไม่เกิน 10 MB',
            'evidenceFiles.*.mimes' => 'รองรับเฉพาะรูปภาพ PDF เอกสาร Office และไฟล์ข้อความ',
        ]);

        $isSubmit = $data['action'] === 'submit';
        if ($isSubmit) {
            $request->validate([
                'periodStart' => ['required', 'date'],
                'periodEnd' => ['required', 'date', 'after_or_equal:periodStart'],
                'progressNote' => ['required', 'string', 'max:10000'],
            ], [
                'periodStart.required' => 'กรุณาเลือกวันที่เริ่มต้น',
                'periodEnd.required' => 'กรุณาเลือกวันที่สิ้นสุด',
                'periodEnd.after_or_equal' => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                'progressNote.required' => 'กรุณาระบุสิ่งที่ทำไปแล้ว',
            ]);
        }

        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $data, $isSubmit, &$storedPaths): void {
                $activity = $this->ownedApprovedActivity((int) $data['activityId']);
                $this->assertTopicExists($activity, (int) $data['topicIndex']);
                $this->assertCompletionEditable((int) $activity->idp_item_id);

                $update = null;
                if (filled($data['updatePublicId'] ?? null)) {
                    $update = DB::table('idp_activity_updates')
                        ->where('public_id', $data['updatePublicId'])
                        ->where('activity_id', $activity->id)
                        ->where('updated_by', $request->user()->id)
                        ->lockForUpdate()
                        ->first();

                    if (! $update || $update->status !== 'draft') {
                        throw ValidationException::withMessages([
                            'updatePublicId' => 'แก้ไขได้เฉพาะบันทึกร่างของคุณเท่านั้น รายการที่ส่งแล้วจะไม่สามารถแก้ไขได้',
                        ]);
                    }
                }

                $now = now();
                $values = [
                    'form_code' => $activity->form_code,
                    'topic_index' => (int) $data['topicIndex'],
                    'period_start' => $data['periodStart'] ?? null,
                    'period_end' => $data['periodEnd'] ?? null,
                    'progress_note' => filled($data['progressNote'] ?? null) ? trim($data['progressNote']) : null,
                    'percent_complete' => null,
                    'updated_by' => $request->user()->id,
                    'status' => $isSubmit ? 'submitted' : 'draft',
                    'submitted_at' => $isSubmit ? $now : null,
                    'current_review_step' => null,
                    'approved_at' => null,
                    'updated_at' => $now,
                ];

                if ($update) {
                    DB::table('idp_activity_updates')->where('id', $update->id)->update($values);
                    $updateId = (int) $update->id;
                } else {
                    $updateId = DB::table('idp_activity_updates')->insertGetId([
                        'public_id' => (string) Str::uuid(),
                        'activity_id' => $activity->id,
                        'submission_version' => 1,
                        ...$values,
                        'created_at' => $now,
                    ]);
                }

                DB::table('idp_activity_update_evidences')
                    ->where('activity_update_id', $updateId)
                    ->where('kind', 'link')
                    ->delete();

                foreach ($data['evidenceLinks'] ?? [] as $link) {
                    DB::table('idp_activity_update_evidences')->insert([
                        'public_id' => (string) Str::uuid(),
                        'activity_update_id' => $updateId,
                        'kind' => 'link',
                        'url' => $link['url'],
                        'description' => filled($link['description'] ?? null) ? trim($link['description']) : null,
                        'uploaded_by' => $request->user()->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                foreach ($request->file('evidenceFiles', []) as $file) {
                    $path = $file->store('idp-evidence/'.$request->user()->id, 'local');
                    if (! $path) {
                        throw ValidationException::withMessages(['evidenceFiles' => 'ไม่สามารถบันทึกไฟล์หลักฐานได้ กรุณาลองใหม่']);
                    }
                    $storedPaths[] = $path;
                    DB::table('idp_activity_update_evidences')->insert([
                        'public_id' => (string) Str::uuid(),
                        'activity_update_id' => $updateId,
                        'kind' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file',
                        'storage_path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('idp_activities')->where('id', $activity->id)->update([
                    'status' => 'in_progress',
                    'updated_at' => $now,
                ]);

            });
        } catch (\Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('success', $isSubmit
            ? 'ส่งอัปเดตความก้าวหน้าแล้ว ผู้ดูแลในสาย IDP สามารถเห็นรายการนี้ได้'
            : 'บันทึกร่างความก้าวหน้าแล้ว');
    }

    public function submitCompletion(Request $request): RedirectResponse
    {
        $data = $request->validate(['idpItemId' => ['required', 'integer']]);
        $roundId = DB::table('assessment_rounds')->where('is_active', true)->orderByDesc('id')->value('id');

        $submittedItemId = null;
        DB::transaction(function () use ($request, $data, $roundId, &$submittedItemId): void {
            $item = DB::table('idp_items')
                ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
                ->leftJoin('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
                ->leftJoin('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
                ->where('idp_items.id', $data['idpItemId'])
                ->where('idps.user_id', $request->user()->id)
                ->when($roundId, fn ($query) => $query->where('assessments.assessment_round_id', $roundId))
                ->where('idp_items.status', 'approved')
                ->select('idp_items.id', 'idps.user_id')
                ->lockForUpdate()
                ->first();
            if (! $item) {
                throw ValidationException::withMessages(['idpItemId' => 'ไม่พบสมรรถนะที่พร้อมส่งผลการพัฒนา']);
            }

            $activities = DB::table('idp_activities')->where('idp_item_id', $item->id)->get(['id', 'form_details']);
            $requiredTopics = $activities->sum(fn (object $activity): int => $this->topicCount($activity->form_details));
            $updatedTopics = DB::table('idp_activity_updates')
                ->whereIn('activity_id', $activities->pluck('id'))
                ->where('status', 'submitted')
                ->get(['activity_id', 'topic_index'])
                ->unique(fn (object $update): string => $update->activity_id.':'.$update->topic_index)
                ->count();
            if ($requiredTopics < 1 || $updatedTopics !== $requiredTopics) {
                throw ValidationException::withMessages([
                    'idpItemId' => 'กรุณาเพิ่มอัปเดตความก้าวหน้าให้ครบทุกหัวข้อก่อนส่งให้หัวหน้าตรวจ',
                ]);
            }

            $completion = DB::table('idp_item_completion_submissions')
                ->where('idp_item_id', $item->id)->lockForUpdate()->first();
            if ($completion && preg_match('/^review_step_\d+$/', (string) $completion->status)) {
                throw ValidationException::withMessages(['idpItemId' => 'ผลการพัฒนาสมรรถนะนี้อยู่ระหว่างการตรวจสอบแล้ว']);
            }
            if ($completion && $completion->status === 'approved') {
                throw ValidationException::withMessages(['idpItemId' => 'ผลการพัฒนาสมรรถนะนี้ได้รับอนุมัติแล้ว']);
            }

            $firstStep = $this->reviewWorkflow->firstStep($item);
            $now = now();
            $values = [
                'submission_version' => (int) ($completion->submission_version ?? 0) + 1,
                'status' => $this->reviewWorkflow->statusForStep($firstStep),
                'current_review_step' => $firstStep,
                'submitted_at' => $now,
                'approved_at' => null,
                'updated_at' => $now,
            ];
            if ($completion) {
                DB::table('idp_item_completion_submissions')->where('id', $completion->id)->update($values);
            } else {
                DB::table('idp_item_completion_submissions')->insert([
                    'public_id' => (string) Str::uuid(),
                    'idp_item_id' => $item->id,
                    ...$values,
                    'created_at' => $now,
                ]);
            }

            $submittedItemId = (int) $item->id;
        });

        if ($submittedItemId) {
            $this->notifications->notifyIdpReviewerOfProgressSubmission($request->user(), $submittedItemId);
        }

        return back()->with('success', 'ส่งผลการพัฒนาสมรรถนะให้หัวหน้าตรวจแล้ว');
    }

    private function ownedApprovedActivity(int $activityId): object
    {
        $roundId = DB::table('assessment_rounds')->where('is_active', true)->orderByDesc('id')->value('id');
        $activity = DB::table('idp_activities')
            ->join('idp_items', 'idp_activities.idp_item_id', '=', 'idp_items.id')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->leftJoin('competency_gaps', 'idp_items.competency_gap_id', '=', 'competency_gaps.id')
            ->leftJoin('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->where('idp_activities.id', $activityId)
            ->where('idps.user_id', auth()->id())
            ->when($roundId, fn ($query) => $query->where('assessments.assessment_round_id', $roundId))
            ->select('idp_activities.id', 'idp_activities.idp_item_id', 'idp_activities.form_code', 'idp_activities.form_details', 'idp_items.status as item_status', 'idps.user_id')
            ->lockForUpdate()
            ->first();
        if (! $activity || $activity->item_status !== 'approved') {
            throw ValidationException::withMessages([
                'activityId' => 'สามารถอัปเดตความก้าวหน้าได้หลังแผนผ่านการอนุมัติครบทุกลำดับแล้ว',
            ]);
        }

        return $activity;
    }

    private function assertTopicExists(object $activity, int $topicIndex): void
    {
        if ($topicIndex >= $this->topicCount($activity->form_details)) {
            throw ValidationException::withMessages(['topicIndex' => 'หัวข้อที่เลือกไม่อยู่ในแผนที่ได้รับอนุมัติ']);
        }
    }

    private function topicCount(?string $formDetails): int
    {
        $details = $formDetails ? json_decode($formDetails, true) : [];

        return max(1, count($details['planRows'] ?? []));
    }

    private function assertCompletionEditable(int $itemId): void
    {
        $status = DB::table('idp_item_completion_submissions')->where('idp_item_id', $itemId)->value('status');
        if ($status === 'approved' || (is_string($status) && preg_match('/^review_step_\d+$/', $status))) {
            throw ValidationException::withMessages([
                'activityId' => 'สมรรถนะนี้ถูกส่งปิดงานแล้ว จึงยังเพิ่มหรือแก้ไขข้อมูลไม่ได้',
            ]);
        }
    }
}
