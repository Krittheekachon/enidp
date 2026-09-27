<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\AssessmentRoundReadinessService;
use App\Services\CompetencyAssessmentSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AssessmentRoundController extends Controller
{
    public function __construct(
        private CompetencyAssessmentSyncService $assessmentSync,
        private AssessmentRoundReadinessService $readiness,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data): void {
            $roundId = DB::table('assessment_rounds')->insertGetId([
                ...$this->roundValues($data, false),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($data['copy_from_round_id'])) {
                $this->copyConfiguration((int) $data['copy_from_round_id'], (int) $roundId);
            }

            if ($data['is_active']) {
                $this->readiness->assertReady((int) $roundId);
                DB::table('assessment_rounds')->lockForUpdate()->get(['id']);
                DB::table('assessment_rounds')->where('is_active', true)->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
                DB::table('assessment_rounds')->where('id', $roundId)->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }
        });

        if ($data['is_active']) {
            $this->assessmentSync->syncAllActiveUsers();
        }

        return back()->with('success', 'สร้างรอบการประเมินเรียบร้อยแล้ว');
    }

    public function update(Request $request, int $round): RedirectResponse
    {
        abort_unless(DB::table('assessment_rounds')->where('id', $round)->exists(), 404);

        $data = $this->validated($request, $round);

        DB::transaction(function () use ($data, $round): void {
            DB::table('assessment_rounds')->lockForUpdate()->get(['id']);
            DB::table('assessment_rounds')->where('id', $round)->update([
                ...$this->roundValues($data, false),
                'updated_at' => now(),
            ]);

            if ($data['is_active']) {
                $this->readiness->assertReady($round);
                DB::table('assessment_rounds')
                    ->where('id', '!=', $round)
                    ->where('is_active', true)
                    ->update(['is_active' => false, 'updated_at' => now()]);
                DB::table('assessment_rounds')->where('id', $round)->update([
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            }
        });

        if ($data['is_active']) {
            $this->assessmentSync->syncAllActiveUsers();
        }

        return back()->with('success', 'บันทึกรอบการประเมินเรียบร้อยแล้ว');
    }

    public function activate(int $round): RedirectResponse
    {
        abort_unless(DB::table('assessment_rounds')->where('id', $round)->exists(), 404);
        $this->readiness->assertReady($round);

        DB::transaction(function () use ($round): void {
            DB::table('assessment_rounds')->lockForUpdate()->get(['id']);
            DB::table('assessment_rounds')->where('is_active', true)->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
            DB::table('assessment_rounds')->where('id', $round)->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
        });

        $this->assessmentSync->syncAllActiveUsers();

        return back()->with('success', 'เปลี่ยนรอบการประเมินที่ใช้งานแล้ว');
    }

    private function validated(Request $request, ?int $round = null): array
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('assessment_rounds', 'name')->ignore($round),
            ],
            'self_assess_start' => [Rule::requiredIf(fn (): bool => $request->boolean('is_active')), 'nullable', 'date'],
            'self_assess_end' => [Rule::requiredIf(fn (): bool => $request->boolean('is_active')), 'nullable', 'date', 'after_or_equal:self_assess_start'],
            'supervisor_assess_end' => [Rule::requiredIf(fn (): bool => $request->boolean('is_active')), 'nullable', 'date', 'after_or_equal:self_assess_end'],
            'is_active' => ['required', 'boolean'],
            'copy_from_round_id' => ['nullable', 'integer', 'exists:assessment_rounds,id'],
        ], [
            'name.required' => 'กรุณาระบุชื่อรอบการประเมิน',
            'name.unique' => 'ชื่อรอบการประเมินนี้ถูกใช้แล้ว',
            'self_assess_start.required' => 'กรุณาระบุวันเริ่มการประเมินก่อนเปิดใช้งานรอบ',
            'self_assess_end.required' => 'กรุณาระบุวันสิ้นสุดการประเมินก่อนเปิดใช้งานรอบ',
            'supervisor_assess_end.required' => 'กรุณาระบุวันสิ้นสุดการตรวจของหัวหน้าก่อนเปิดใช้งานรอบ',
            'self_assess_end.after_or_equal' => 'วันสิ้นสุดการประเมินต้องไม่ก่อนวันเริ่มการประเมิน',
            'supervisor_assess_end.after_or_equal' => 'วันสิ้นสุดการตรวจของหัวหน้าต้องไม่ก่อนวันสิ้นสุดการประเมิน',
        ]);

        $storedYear = $round ? (int) DB::table('assessment_rounds')->where('id', $round)->value('year') : null;
        $hasAssessments = $round && DB::table('assessments')->where('assessment_round_id', $round)->exists();
        $startDate = $data['self_assess_start'] ?? null;
        $data['year'] = $hasAssessments
            ? $storedYear
            : ($startDate
                ? CarbonImmutable::parse($startDate)->year + 543
                : ($storedYear ?: now()->year + 543));

        return $data;
    }

    private function roundValues(array $data, ?bool $isActive = null): array
    {
        return [
            'name' => trim($data['name']),
            'year' => (int) $data['year'],
            'self_assess_start' => $data['self_assess_start'] ?? null,
            'self_assess_end' => $data['self_assess_end'] ?? null,
            'supervisor_assess_end' => $data['supervisor_assess_end'] ?? null,
            'is_active' => $isActive ?? (bool) $data['is_active'],
        ];
    }

    private function copyConfiguration(int $sourceRoundId, int $targetRoundId): void
    {
        $now = now();
        $competencies = DB::table('position_competencies')
            ->where('assessment_round_id', $sourceRoundId)
            ->get(['position_id', 'competency_id'])
            ->map(fn (object $row): array => [
                'assessment_round_id' => $targetRoundId,
                'position_id' => $row->position_id,
                'competency_id' => $row->competency_id,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($competencies !== []) {
            DB::table('position_competencies')->insertOrIgnore($competencies);
        }

        DB::table('position_fc_selection_rules')
            ->where('assessment_round_id', $sourceRoundId)
            ->get(['position_id', 'required_fc_count'])
            ->each(function (object $rule) use ($targetRoundId, $now): void {
                DB::table('position_fc_selection_rules')->insertOrIgnore([
                    'assessment_round_id' => $targetRoundId,
                    'position_id' => $rule->position_id,
                    'required_fc_count' => $rule->required_fc_count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }
}
