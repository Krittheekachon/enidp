<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\AssessmentRoundWindow;
use App\Services\IdpItemReviewWorkflow;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class IdpController extends Controller
{
    private const CANONICAL_LEARNING_METHOD_KEYS = [
        'experiential-learning',
        'social-learning',
        'formal-learning',
    ];

    public function __construct(
        private readonly IdpItemReviewWorkflow $reviewWorkflow,
        private readonly AssessmentRoundWindow $assessmentRoundWindow,
        private readonly NotificationService $notifications,
    ) {
    }

    public function saveDraft(Request $request): RedirectResponse|JsonResponse
    {
        $this->persist($request, 'draft');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'บันทึกร่างแผน IDP แล้ว',
                'savedAt' => now()->toIso8601String(),
            ]);
        }

        return back()->with('success', 'บันทึกร่างแผน IDP แล้ว');
    }

    public function submit(Request $request): RedirectResponse
    {
        $submittedItemIds = $this->persist($request, 'submitted');
        foreach ($submittedItemIds as $idpItemId) {
            $this->notifications->notifyIdpReviewerForItem($request->user(), $idpItemId);
        }

        return back()->with('success', 'ส่งแผน IDP ให้หัวหน้าอนุมัติแล้ว');
    }

    public function submitItem(Request $request): RedirectResponse
    {
        $request->merge([
            'items' => [$request->input('item', [])],
        ]);
        $submittedItemIds = $this->persist($request, 'submitted');
        foreach ($submittedItemIds as $idpItemId) {
            $this->notifications->notifyIdpReviewerForItem($request->user(), $idpItemId);
        }

        return back()->with('success', 'ส่งแผนสมรรถนะนี้ให้หัวหน้าอนุมัติแล้ว');
    }

    private function persist(Request $request, string $status): array
    {
        $required = $status === 'submitted' ? 'required' : 'nullable';
        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.competencyGapId' => ['required', 'integer'],
            'items.*.goal' => [$required, 'string'],
            'items.*.successCriteria' => [$required, 'string'],
            'items.*.activities' => ['present', 'array'],
            'items.*.activities.*.methodKey' => [$required, 'string', 'max:255'],
            'items.*.activities.*.developmentToolId' => ['nullable', 'integer', 'exists:idp_learning_methods,id'],
            'items.*.activities.*.learningCatalogId' => ['nullable', 'integer', 'exists:learning_catalogs,id'],
            'items.*.activities.*.activityName' => ['nullable', 'string', 'max:255'],
            'items.*.activities.*.activityDescription' => ['nullable', 'string'],
            'items.*.activities.*.documentReferenceNumber' => ['nullable', 'string', 'max:255'],
            'items.*.activities.*.weightPercent' => [$required, 'integer', 'min:1', 'max:100'],
            'items.*.activities.*.startDate' => ['nullable', 'date'],
            'items.*.activities.*.endDate' => ['nullable', 'date'],
            'items.*.activities.*.formCode' => ['nullable', 'string', 'max:120'],
            'items.*.activities.*.formDetails' => ['nullable', 'array'],
        ]);

        $items = collect($validated['items'] ?? [])->values();
        $this->validateUniqueGaps($items);
        $validGaps = $this->validGapsForCurrentUser($items->pluck('competencyGapId')->unique()->values());

        if ($items->isNotEmpty() && $validGaps->count() !== $items->pluck('competencyGapId')->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'ไม่สามารถจัดทำ IDP จาก Gap ที่ไม่ใช่ของผู้ใช้ปัจจุบัน หรือผลประเมินยังไม่อนุมัติ',
            ]);
        }

        if ($status === 'submitted' && $items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'ไม่มีรายการ Gap ที่ต้องจัดทำ IDP',
            ]);
        }

        $submittedMethodKeys = $items
            ->flatMap(fn (array $item) => $item['activities'] ?? [])
            ->pluck('methodKey')
            ->filter()
            ->unique()
            ->values();

        $invalidMethodKeys = $submittedMethodKeys->diff(self::CANONICAL_LEARNING_METHOD_KEYS);
        if ($invalidMethodKeys->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'ประเภทการเรียนรู้ต้องเป็น Experiential, Social หรือ Formal เท่านั้น',
            ]);
        }

        $methodIdsByKey = DB::table('learning_method_types')
            ->whereIn('key', $submittedMethodKeys)
            ->pluck('id', 'key');
        $toolColumns = ['id', 'focus_type', 'title'];
        if (Schema::hasColumn('idp_learning_methods', 'form_code')) {
            $toolColumns[] = 'form_code';
        }
        $toolsById = DB::table('idp_learning_methods')
            ->whereIn('id', $items->flatMap(fn (array $item) => $item['activities'] ?? [])->pluck('developmentToolId')->filter()->unique())
            ->where('is_active', true)
            ->get($toolColumns)
            ->keyBy('id');
        $catalogCompetencies = DB::table('learning_catalog_competency')
            ->join('learning_catalogs', 'learning_catalog_competency.learning_catalog_id', '=', 'learning_catalogs.id')
            ->whereIn('learning_catalog_id', $items->flatMap(fn (array $item) => $item['activities'] ?? [])->pluck('learningCatalogId')->filter()->unique())
            ->where('learning_catalogs.is_active', true)
            ->get(['learning_catalog_competency.learning_catalog_id', 'learning_catalog_competency.competency_id'])
            ->groupBy('learning_catalog_id')
            ->map(fn ($rows) => $rows->pluck('competency_id')->map(fn ($id) => (int) $id));
        $catalogsById = DB::table('learning_catalogs')
            ->whereIn('id', $items->flatMap(fn (array $item) => $item['activities'] ?? [])->pluck('learningCatalogId')->filter()->unique())
            ->where('is_active', true)
            ->get(['id', 'code', 'name', 'delivery_type', 'cost', 'hours', 'expected_levels', 'description'])
            ->keyBy('id');

        $items = $this->withAuthoritativeActivityData($items, $toolsById, $catalogsById);

        $this->validateActivities(
            $items,
            $status,
            $validGaps,
            $methodIdsByKey,
            $toolsById,
            $catalogCompetencies,
            $catalogsById,
        );
        $owner = DB::table('users')
            ->where('id', auth()->id())
            ->first(['id']);

        $submittedItemIds = [];

        DB::transaction(function () use ($items, $status, $methodIdsByKey, $toolsById, $catalogsById, $owner, &$submittedItemIds): void {
            $idpId = $this->currentUserIdpId();
            foreach ($items as $item) {
                $gapId = (int) $item['competencyGapId'];
                $existing = DB::table('idp_items')
                    ->where('idp_id', $idpId)
                    ->where('competency_gap_id', $gapId)
                    ->orderByDesc('id')
                    ->first();

                if ($existing && ($existing->status === 'approved'
                    || $this->reviewWorkflow->isUnderReview($existing->status))) {
                    continue;
                }

                $isSubmission = $status === 'submitted';
                $firstReviewStep = $isSubmission
                    ? $this->reviewWorkflow->firstStep($owner)
                    : null;
                $itemStatus = $isSubmission
                    ? $this->reviewWorkflow->statusForStep($firstReviewStep)
                    : (($existing->status ?? null) === 'revision_required'
                        ? 'revision_required'
                        : 'draft');
                $values = [
                    'behavior_key' => 'competency-gap:'.$item['competencyGapId'],
                    'behavior_description' => null,
                    'goal' => $item['goal'] ?? null,
                    'success_criteria' => $item['successCriteria'] ?? null,
                    'status' => $itemStatus,
                    'submission_version' => $isSubmission
                        ? ((int) ($existing->submission_version ?? 0) + 1)
                        : (int) ($existing->submission_version ?? 0),
                    'current_review_step' => $firstReviewStep,
                    'submitted_at' => $isSubmission
                        ? now()
                        : ($existing->submitted_at ?? null),
                    'updated_at' => now(),
                ];

                if ($isSubmission) {
                    $values = [
                        ...$values,
                        'approved_by' => null,
                        'approved_at' => null,
                        'rejected_by' => null,
                        'rejected_at' => null,
                        'reject_comment' => null,
                    ];
                }

                if ($existing) {
                    DB::table('idp_items')->where('id', $existing->id)->update($values);
                    $itemId = (int) $existing->id;
                    DB::table('idp_activities')->where('idp_item_id', $itemId)->delete();
                } else {
                    $itemId = DB::table('idp_items')->insertGetId([
                        'idp_id' => $idpId,
                        'competency_gap_id' => $gapId,
                        ...$values,
                        'created_at' => now(),
                    ]);
                }

                if ($isSubmission) {
                    $submittedItemIds[] = $itemId;
                }

                foreach ($item['activities'] ?? [] as $activity) {
                    $tool = ! empty($activity['developmentToolId'])
                        ? $toolsById->get($activity['developmentToolId'])
                        : null;
                    $catalog = ! empty($activity['learningCatalogId'])
                        ? $catalogsById->get($activity['learningCatalogId'])
                        : null;
                    $activityName = $catalog?->name;
                    if ($tool) {
                        $activityName = $tool->title;
                    }

                    DB::table('idp_activities')->insert([
                        'idp_item_id' => $itemId,
                        'learning_catalog_id' => $activity['learningCatalogId'] ?? null,
                        'method_type_id' => $methodIdsByKey[$activity['methodKey'] ?? ''] ?? null,
                        'idp_learning_method_id' => $activity['developmentToolId'] ?? null,
                        'activity_name' => $activityName,
                        'weight_percent' => $activity['weightPercent'] ?? null,
                        'start_date' => in_array(($activity['formCode'] ?? null), ['form_3_project_assignment', 'form_4_ojt', 'form_5_coaching', 'form_6_mentoring', 'form_7_group_activity', 'form_8_feedback', 'form_9_field_trip', 'form_10_training'], true)
                            ? collect($activity['formDetails']['planRows'] ?? [])->pluck('developmentStart')->filter()->sort()->first()
                            : ($activity['startDate'] ?? null),
                        'end_date' => in_array(($activity['formCode'] ?? null), ['form_3_project_assignment', 'form_4_ojt', 'form_5_coaching', 'form_6_mentoring', 'form_7_group_activity', 'form_8_feedback', 'form_9_field_trip', 'form_10_training'], true)
                            ? collect($activity['formDetails']['planRows'] ?? [])->pluck('developmentEnd')->filter()->sort()->last()
                            : ($activity['endDate'] ?? null),
                        'description' => $tool ? null : $catalog?->description,
                        'document_reference_number' => in_array(($activity['formCode'] ?? null), ['form_3_project_assignment', 'form_4_ojt', 'form_5_coaching', 'form_6_mentoring', 'form_7_group_activity', 'form_8_feedback', 'form_9_field_trip', 'form_10_training'], true)
                            ? null
                            : ($activity['documentReferenceNumber'] ?? null),
                        'form_code' => $activity['formCode'] ?? null,
                        'form_details' => isset($activity['formDetails'])
                            ? json_encode($activity['formDetails'], JSON_UNESCAPED_UNICODE)
                            : null,
                        'status' => 'planned',
                        'result' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->reviewWorkflow->syncParentStatus($idpId);
        });

        return $submittedItemIds;
    }

    private function validateUniqueGaps(Collection $items): void
    {
        if ($items->pluck('competencyGapId')->filter()->count() !== $items->pluck('competencyGapId')->filter()->unique()->count()) {
            throw ValidationException::withMessages([
                'items' => 'หนึ่งสมรรถนะสามารถมีแผน IDP ได้เพียงหนึ่งแผน',
            ]);
        }
    }

    private function withAuthoritativeActivityData(Collection $items, Collection $toolsById, Collection $catalogsById): Collection
    {
        return $items->map(function (array $item) use ($toolsById, $catalogsById): array {
            $item['activities'] = collect($item['activities'] ?? [])->map(function (array $activity) use ($toolsById, $catalogsById): array {
                $tool = ! empty($activity['developmentToolId'])
                    ? $toolsById->get($activity['developmentToolId'])
                    : null;
                $catalog = ! empty($activity['learningCatalogId'])
                    ? $catalogsById->get($activity['learningCatalogId'])
                    : null;

                if ($catalog) {
                    $details = is_array($activity['formDetails'] ?? null) ? $activity['formDetails'] : [];
                    $rows = collect($details['planRows'] ?? [])->values();
                    $userRow = is_array($rows->first()) ? $rows->first() : [];
                    $details['planRows'] = [[
                        ...$userRow,
                        'trainingType' => $catalog->delivery_type === 'in_class' ? 'In-class Training' : 'e-Learning',
                        'courseCode' => $catalog->code ?? '',
                        'courseName' => $catalog->name ?? '',
                        'courseDescription' => $catalog->description ?? '',
                        'hours' => $catalog->hours,
                        'cost' => $catalog->cost,
                    ]];
                    $details['_formCode'] = 'form_10_training';

                    return [
                        ...$activity,
                        'activityName' => $catalog->name ?? '',
                        'activityDescription' => $catalog->description ?? '',
                        'formCode' => 'form_10_training',
                        'formDetails' => $details,
                    ];
                }

                if ($tool) {
                    return [
                        ...$activity,
                        'activityName' => $tool->title ?? '',
                        'activityDescription' => '',
                        'formCode' => $tool->form_code ?? '',
                    ];
                }

                return [
                    ...$activity,
                    'activityName' => '',
                    'activityDescription' => '',
                    'formCode' => '',
                ];
            })->values()->all();

            return $item;
        })->values();
    }

    private function decodeExpectedLevels(mixed $levels): array
    {
        $decoded = is_string($levels) ? json_decode($levels, true) : $levels;

        return collect(is_array($decoded) ? $decoded : [])
            ->map(fn ($level) => (int) $level)
            ->filter(fn (int $level) => $level >= 1 && $level <= 5)
            ->unique()
            ->values()
            ->all();
    }

    private function validateActivities(
        Collection $items,
        string $status,
        Collection $validGaps,
        Collection $methodIdsByKey,
        Collection $toolsById,
        Collection $catalogCompetencies,
        Collection $catalogsById,
    ): void {
        foreach ($items as $itemIndex => $item) {
            $activities = collect($item['activities'] ?? [])->values();

            if ($status === 'submitted' && $activities->isEmpty()) {
                throw ValidationException::withMessages([
                    "items.$itemIndex.activities" => 'ต้องมีกิจกรรมพัฒนาอย่างน้อยหนึ่งรายการ',
                ]);
            }

            if ($status === 'submitted' && round((float) $activities->sum('weightPercent'), 2) !== 100.0) {
                throw ValidationException::withMessages([
                    "items.$itemIndex.activities" => 'น้ำหนักกิจกรรมของแต่ละสมรรถนะต้องรวม 100%',
                ]);
            }

            $gap = $validGaps[$item['competencyGapId']] ?? null;
            $competencyId = (int) ($gap->competency_id ?? 0);
            $expectedLevel = (int) ($gap->expected_level ?? 0);

            foreach ($activities as $activityIndex => $activity) {
                $prefix = "items.$itemIndex.activities.$activityIndex";
                $methodKey = $activity['methodKey'] ?? '';
                $focusType = $this->focusTypeForMethodKey($methodKey);
                $toolId = $activity['developmentToolId'] ?? null;
                $tool = $toolId ? $toolsById->get($toolId) : null;
                $catalogId = $activity['learningCatalogId'] ?? null;
                $catalog = $catalogId ? $catalogsById->get($catalogId) : null;

                if ($toolId && $catalogId) {
                    throw ValidationException::withMessages([
                        "$prefix.developmentToolId" => 'หนึ่งกิจกรรมเลือกได้เพียงเครื่องมือหรือหลักสูตรอย่างใดอย่างหนึ่ง',
                    ]);
                }

                if ($methodKey !== '' && (! in_array($methodKey, self::CANONICAL_LEARNING_METHOD_KEYS, true) || ! $methodIdsByKey->has($methodKey))) {
                    throw ValidationException::withMessages([
                        "$prefix.methodKey" => 'ไม่พบประเภทการเรียนรู้ที่เลือก',
                    ]);
                }

                if (in_array($focusType, ['experiential', 'social'], true)) {
                    if ($status === 'submitted' && ! $toolId) {
                        throw ValidationException::withMessages([
                            "$prefix.developmentToolId" => 'กรุณาเลือกเครื่องมือหรือแนวทางการพัฒนา',
                        ]);
                    }
                    if ($toolId && (! $tool || ($tool->focus_type ?? null) !== $focusType)) {
                        throw ValidationException::withMessages([
                            "$prefix.developmentToolId" => 'เครื่องมือพัฒนาไม่ตรงกับประเภทการเรียนรู้',
                        ]);
                    }
                    if ($catalogId) {
                        throw ValidationException::withMessages([
                            "$prefix.learningCatalogId" => 'Learning Catalog ใช้ได้กับ Formal Learning เท่านั้น',
                        ]);
                    }
                }

                if ($focusType === 'formal') {
                    if ($status === 'submitted' && ! $catalogId) {
                        throw ValidationException::withMessages([
                            "$prefix.learningCatalogId" => 'กรุณาเลือกหลักสูตรจาก Learning Catalog',
                        ]);
                    }
                    if ($catalogId && (! $catalog || ! ($catalogCompetencies[$catalogId] ?? collect())->contains($competencyId))) {
                        throw ValidationException::withMessages([
                            "$prefix.learningCatalogId" => 'หลักสูตรนี้ไม่ได้ผูกกับสมรรถนะที่ต้องพัฒนา',
                        ]);
                    }
                    $catalogExpectedLevels = $this->decodeExpectedLevels($catalog?->expected_levels ?? null);
                    if ($catalogId && $catalogExpectedLevels && ! in_array($expectedLevel, $catalogExpectedLevels, true)) {
                        throw ValidationException::withMessages([
                            "$prefix.learningCatalogId" => 'หลักสูตรนี้ไม่รองรับระดับความคาดหวังของผู้ใช้',
                        ]);
                    }
                    if ($toolId) {
                        throw ValidationException::withMessages([
                            "$prefix.developmentToolId" => 'Formal Learning ต้องเลือกจาก Learning Catalog',
                        ]);
                    }
                }

                $requiresForm = $focusType === 'formal'
                    || (in_array($focusType, ['experiential', 'social'], true)
                        && ! empty($tool?->form_code));

                if ($status === 'submitted' && $requiresForm && empty($activity['formCode'])) {
                    throw ValidationException::withMessages([
                        "$prefix.formCode" => 'กรุณากรอกรายละเอียดฟอร์มกิจกรรม',
                    ]);
                }

                if ($status === 'submitted' && $requiresForm && empty($activity['formDetails']['_saved'])) {
                    throw ValidationException::withMessages([
                        "$prefix.formDetails" => 'กรุณาบันทึกฟอร์มกิจกรรมก่อนส่งแผน',
                    ]);
                }

                if ($status === 'submitted' && ($activity['formCode'] ?? null) === 'form_3_project_assignment') {
                    $rows = collect($activity['formDetails']['planRows'] ?? [])->values();
                    if ($rows->isEmpty()) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.planRows" => 'กรุณาเพิ่มงานที่ได้รับมอบหมายอย่างน้อย 1 รายการ',
                        ]);
                    }

                    foreach ($rows as $rowIndex => $row) {
                        foreach ([
                            'assignmentTopic' => 'หัวข้องานโครงการ/งานพิเศษที่ได้รับมอบหมาย',
                            'developmentGoal' => 'เป้าหมายในการพัฒนา',
                            'developmentApproach' => 'วิธีดำเนินการ',
                            'developmentStart' => 'วันที่เริ่มต้น',
                            'developmentEnd' => 'วันที่สิ้นสุด',
                        ] as $field => $label) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages([
                                    "$prefix.formDetails.planRows.$rowIndex.$field" => "กรุณากรอก{$label}",
                                ]);
                            }
                        }

                        if (($row['developmentEnd'] ?? '') < ($row['developmentStart'] ?? '')) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.developmentEnd" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                            ]);
                        }

                    }
                }

                if ($status === 'submitted' && ($activity['formCode'] ?? null) === 'form_4_ojt') {
                    $details = $activity['formDetails']['detail'] ?? [];
                    $trainerType = $details['trainerType'] ?? null;
                    if (! in_array($trainerType, ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'], true)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.trainerType" => 'กรุณาเลือกประเภทผู้สอนงาน',
                        ]);
                    }
                    if ($trainerType === 'ผู้เชี่ยวชาญ' && blank($details['trainerExpertName'] ?? null)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.trainerExpertName" => 'กรุณากรอกชื่อผู้เชี่ยวชาญ',
                        ]);
                    }

                    $rows = collect($activity['formDetails']['planRows'] ?? [])->values();
                    if ($rows->isEmpty()) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.planRows" => 'กรุณาเพิ่มหัวข้อฝึกปฏิบัติอย่างน้อย 1 รายการ',
                        ]);
                    }

                    foreach ($rows as $rowIndex => $row) {
                        foreach ([
                            'skillTopic' => 'หัวข้อทักษะ/ประเด็นการฝึกปฏิบัติงาน',
                            'developmentStart' => 'วันที่เริ่มต้น',
                            'developmentEnd' => 'วันที่สิ้นสุด',
                            'hours' => 'จำนวนชั่วโมง',
                            'developmentGoal' => 'เป้าหมายในการพัฒนา',
                            'developmentApproach' => 'วิธีการ',
                        ] as $field => $label) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages([
                                    "$prefix.formDetails.planRows.$rowIndex.$field" => "กรุณากรอก{$label}",
                                ]);
                            }
                        }

                        if (! is_numeric($row['hours'] ?? null) || (float) $row['hours'] <= 0) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.hours" => 'จำนวนชั่วโมงต้องมากกว่า 0',
                            ]);
                        }

                        if (($row['developmentEnd'] ?? '') < ($row['developmentStart'] ?? '')) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.developmentEnd" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                            ]);
                        }

                    }
                }

                if ($status === 'submitted' && ($activity['formCode'] ?? null) === 'form_5_coaching') {
                    $details = $activity['formDetails']['detail'] ?? [];
                    $coachType = $details['coachType'] ?? null;
                    if (! in_array($coachType, ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'], true)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.coachType" => 'กรุณาเลือกประเภทผู้สอนงาน',
                        ]);
                    }
                    if ($coachType === 'ผู้เชี่ยวชาญ' && blank($details['coachExpertName'] ?? null)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.coachExpertName" => 'กรุณากรอกชื่อผู้เชี่ยวชาญ',
                        ]);
                    }

                    $rows = collect($activity['formDetails']['planRows'] ?? [])->values();
                    if ($rows->isEmpty()) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.planRows" => 'กรุณาเพิ่มหัวข้อการสอนงานอย่างน้อย 1 รายการ',
                        ]);
                    }

                    foreach ($rows as $rowIndex => $row) {
                        foreach ([
                            'topic' => 'หัวข้อทักษะ/ประเด็นการสอนงาน',
                            'developmentStart' => 'วันที่เริ่มต้น',
                            'developmentEnd' => 'วันที่สิ้นสุด',
                            'sessionCount' => 'จำนวนครั้ง',
                            'sessionDuration' => 'ระยะเวลาต่อครั้ง',
                            'developmentGoal' => 'เป้าหมายในการพัฒนา',
                            'developmentApproach' => 'วิธีดำเนินการ',
                        ] as $field => $label) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages([
                                    "$prefix.formDetails.planRows.$rowIndex.$field" => "กรุณากรอก{$label}",
                                ]);
                            }
                        }

                        $approaches = collect($row['coachingApproaches'] ?? [])->filter()->unique()->values();
                        if ($approaches->isEmpty() || $approaches->diff(['A', 'B', 'C', 'D'])->isNotEmpty()) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.coachingApproaches" => 'กรุณาเลือกแนวทางการสอนงาน A–D อย่างน้อย 1 ข้อ',
                            ]);
                        }

                        if (($row['developmentEnd'] ?? '') < ($row['developmentStart'] ?? '')) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.developmentEnd" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                            ]);
                        }

                        if (filter_var($row['sessionCount'] ?? null, FILTER_VALIDATE_INT) === false || (int) $row['sessionCount'] < 1) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.sessionCount" => 'จำนวนครั้งต้องไม่น้อยกว่า 1',
                            ]);
                        }
                    }
                }

                if ($status === 'submitted' && ($activity['formCode'] ?? null) === 'form_6_mentoring') {
                    $details = $activity['formDetails']['detail'] ?? [];
                    $mentorType = $details['mentorType'] ?? null;
                    if (! in_array($mentorType, ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'], true)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.mentorType" => 'กรุณาเลือกประเภทผู้สอนงาน',
                        ]);
                    }
                    if ($mentorType === 'ผู้เชี่ยวชาญ' && blank($details['mentorExpertName'] ?? null)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.mentorExpertName" => 'กรุณากรอกชื่อผู้เชี่ยวชาญ',
                        ]);
                    }

                    $rows = collect($activity['formDetails']['planRows'] ?? [])->values();
                    if ($rows->isEmpty()) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.planRows" => 'กรุณาเพิ่มหัวข้อที่ต้องการพัฒนาอย่างน้อย 1 รายการ',
                        ]);
                    }

                    foreach ($rows as $rowIndex => $row) {
                        foreach ([
                            'skillTopic' => 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา',
                            'technique' => 'เทคนิค',
                            'developmentStart' => 'วันที่เริ่มต้น',
                            'developmentEnd' => 'วันที่สิ้นสุด',
                            'sessionCount' => 'จำนวนครั้ง',
                            'sessionDuration' => 'ระยะเวลาต่อครั้ง',
                            'developmentGoal' => 'เป้าหมายในการพัฒนา',
                        ] as $field => $label) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages([
                                    "$prefix.formDetails.planRows.$rowIndex.$field" => "กรุณากรอก{$label}",
                                ]);
                            }
                        }

                        if (($row['developmentEnd'] ?? '') < ($row['developmentStart'] ?? '')) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.developmentEnd" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                            ]);
                        }

                        if (filter_var($row['sessionCount'] ?? null, FILTER_VALIDATE_INT) === false || (int) $row['sessionCount'] < 1) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.sessionCount" => 'จำนวนครั้งต้องไม่น้อยกว่า 1',
                            ]);
                        }
                    }
                }

                if ($status === 'submitted' && ($activity['formCode'] ?? null) === 'form_7_group_activity') {
                    $details = $activity['formDetails']['detail'] ?? [];
                    $facilitatorType = $details['facilitatorType'] ?? null;
                    if (! in_array($facilitatorType, ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'], true)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.facilitatorType" => 'กรุณาเลือกประเภทผู้อำนวยการ/ผู้นำกิจกรรม',
                        ]);
                    }
                    if ($facilitatorType === 'ผู้เชี่ยวชาญ' && blank($details['facilitatorExpertName'] ?? null)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.facilitatorExpertName" => 'กรุณากรอกชื่อผู้เชี่ยวชาญ',
                        ]);
                    }

                    $rows = collect($activity['formDetails']['planRows'] ?? [])->values();
                    if ($rows->isEmpty()) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.planRows" => 'กรุณาเพิ่มกิจกรรมอย่างน้อย 1 รายการ',
                        ]);
                    }

                    foreach ($rows as $rowIndex => $row) {
                        foreach ([
                            'learningTopic' => 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา',
                            'technique' => 'เทคนิค',
                            'developmentStart' => 'วันที่เริ่มต้น',
                            'developmentEnd' => 'วันที่สิ้นสุด',
                            'assessmentTools' => 'เครื่องมือและเงื่อนไขการประเมิน',
                            'developmentGoal' => 'เป้าหมายในการพัฒนา',
                        ] as $field => $label) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages([
                                    "$prefix.formDetails.planRows.$rowIndex.$field" => "กรุณากรอก{$label}",
                                ]);
                            }
                        }

                        if (($row['developmentEnd'] ?? '') < ($row['developmentStart'] ?? '')) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.developmentEnd" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                            ]);
                        }
                    }
                }

                if ($status === 'submitted' && ($activity['formCode'] ?? null) === 'form_8_feedback') {
                    $details = $activity['formDetails']['detail'] ?? [];
                    $providerType = $details['feedbackProviderType'] ?? null;
                    if (! in_array($providerType, ['ผู้บังคับบัญชา', 'ผู้เชี่ยวชาญ'], true)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.feedbackProviderType" => 'กรุณาเลือกประเภทผู้ให้ข้อมูล',
                        ]);
                    }
                    if ($providerType === 'ผู้เชี่ยวชาญ' && blank($details['feedbackExpertName'] ?? null)) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.detail.feedbackExpertName" => 'กรุณากรอกชื่อผู้เชี่ยวชาญ',
                        ]);
                    }

                    $rows = collect($activity['formDetails']['planRows'] ?? [])->values();
                    if ($rows->isEmpty()) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.planRows" => 'กรุณาเพิ่มหัวข้อการพัฒนาอย่างน้อย 1 รายการ',
                        ]);
                    }

                    foreach ($rows as $rowIndex => $row) {
                        foreach ([
                            'skillTopic' => 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา',
                            'feedbackSource' => 'แหล่งข้อมูลป้อนกลับ',
                            'developmentStart' => 'วันที่เริ่มต้น',
                            'developmentEnd' => 'วันที่สิ้นสุด',
                            'sessionCount' => 'จำนวนครั้ง',
                            'sessionDuration' => 'ระยะเวลาต่อครั้ง',
                            'developmentGoal' => 'เป้าหมายในการพัฒนา',
                        ] as $field => $label) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages([
                                    "$prefix.formDetails.planRows.$rowIndex.$field" => "กรุณากรอก{$label}",
                                ]);
                            }
                        }

                        if (($row['developmentEnd'] ?? '') < ($row['developmentStart'] ?? '')) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.developmentEnd" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                            ]);
                        }

                        if (filter_var($row['sessionCount'] ?? null, FILTER_VALIDATE_INT) === false || (int) $row['sessionCount'] < 1) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.sessionCount" => 'จำนวนครั้งต้องไม่น้อยกว่า 1',
                            ]);
                        }
                    }
                }

                if ($status === 'submitted' && ($activity['formCode'] ?? null) === 'form_9_field_trip') {
                    $rows = collect($activity['formDetails']['planRows'] ?? [])->values();
                    if ($rows->isEmpty()) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.planRows" => 'กรุณาเพิ่มรายการศึกษาดูงานอย่างน้อย 1 รายการ',
                        ]);
                    }

                    foreach ($rows as $rowIndex => $row) {
                        foreach ([
                            'skillTopic' => 'หัวข้อทักษะ/ประเด็นที่ต้องการพัฒนา',
                            'learningPlace' => 'สถานที่/แหล่งศึกษาดูงาน',
                            'developmentStart' => 'วันที่เริ่มต้น',
                            'developmentEnd' => 'วันที่สิ้นสุด',
                            'assessmentTools' => 'เครื่องมือและเงื่อนไขการประเมิน',
                            'developmentGoal' => 'เป้าหมายในการพัฒนา',
                        ] as $field => $label) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages([
                                    "$prefix.formDetails.planRows.$rowIndex.$field" => "กรุณากรอก{$label}",
                                ]);
                            }
                        }

                        if (($row['developmentEnd'] ?? '') < ($row['developmentStart'] ?? '')) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.developmentEnd" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                            ]);
                        }
                    }
                }

                if ($status === 'submitted' && ($activity['formCode'] ?? null) === 'form_10_training') {
                    $rows = collect($activity['formDetails']['planRows'] ?? [])->values();
                    if ($rows->isEmpty()) {
                        throw ValidationException::withMessages([
                            "$prefix.formDetails.planRows" => 'กรุณาเพิ่มหลักสูตรอบรมอย่างน้อย 1 รายการ',
                        ]);
                    }

                    foreach ($rows as $rowIndex => $row) {
                        foreach ([
                            'developmentStart' => 'วันที่เริ่มต้น',
                            'developmentEnd' => 'วันที่สิ้นสุด',
                            'developmentGoal' => 'เป้าหมายในการพัฒนา',
                        ] as $field => $label) {
                            if (blank($row[$field] ?? null)) {
                                throw ValidationException::withMessages([
                                    "$prefix.formDetails.planRows.$rowIndex.$field" => "กรุณากรอก{$label}",
                                ]);
                            }
                        }

                        if (($row['developmentEnd'] ?? '') < ($row['developmentStart'] ?? '')) {
                            throw ValidationException::withMessages([
                                "$prefix.formDetails.planRows.$rowIndex.developmentEnd" => 'วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่มต้น',
                            ]);
                        }
                    }
                }
            }
        }
    }

    private function focusTypeForMethodKey(string $methodKey): string
    {
        return match ($methodKey) {
            'experiential-learning' => 'experiential',
            'social-learning' => 'social',
            'formal-learning' => 'formal',
            default => '',
        };
    }

    private function validGapsForCurrentUser(Collection $gapIds): Collection
    {
        if ($gapIds->isEmpty()) {
            return collect();
        }

        $roundId = (int) $this->assessmentRoundWindow->activeRound()->id;

        return DB::table('competency_gaps')
            ->join('assessments', 'competency_gaps.assessment_id', '=', 'assessments.id')
            ->where('assessments.user_id', auth()->id())
            ->where('assessments.assessment_round_id', $roundId)
            ->whereIn('competency_gaps.id', $gapIds)
            ->where('competency_gaps.requires_idp', true)
            ->where('competency_gaps.gap', '<', 0)
            ->whereIn('competency_gaps.status', ['approved', 'dean_approved'])
            ->get([
                'competency_gaps.id',
                'competency_gaps.competency_id',
                'competency_gaps.expected_level',
            ])
            ->keyBy('id');
    }

    private function currentUserIdpId(): int
    {
        $round = $this->assessmentRoundWindow->activeRound();
        $roundId = (int) $round->id;
        $year = (int) $round->year;
        $existing = DB::table('idps')
            ->where('user_id', auth()->id())
            ->where(function ($query) use ($roundId): void {
                $query->where('assessment_round_id', $roundId)
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
            })
            ->orderByDesc('id')
            ->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('idps')->insertGetId([
            'user_id' => auth()->id(),
            'assessment_round_id' => $roundId,
            'year' => $year,
            'status' => 'draft',
            'submitted_at' => null,
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

}
