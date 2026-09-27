<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssessmentRoundReadinessService
{
    public function check(int $roundId): array
    {
        $round = DB::table('assessment_rounds')->where('id', $roundId)->first([
            'id',
            'self_assess_start',
            'self_assess_end',
            'supervisor_assess_end',
        ]);

        if (! $round) {
            return [
                'ready' => false,
                'issues' => [['key' => 'round', 'count' => 1, 'message' => 'ไม่พบรอบการประเมิน']],
            ];
        }

        $issues = collect();
        $missingDates = collect([
            $round->self_assess_start,
            $round->self_assess_end,
            $round->supervisor_assess_end,
        ])->filter(fn ($date): bool => blank($date))->count();

        if ($missingDates > 0) {
            $issues->push([
                'key' => 'dates',
                'count' => $missingDates,
                'message' => 'กรุณากำหนดวันเริ่ม วันสิ้นสุดการประเมิน และวันสิ้นสุดการตรวจของหัวหน้าให้ครบ',
            ]);
        } else {
            $timezone = config('app.timezone');
            $selfStart = CarbonImmutable::parse($round->self_assess_start, $timezone)->startOfDay();
            $selfEnd = CarbonImmutable::parse($round->self_assess_end, $timezone)->startOfDay();
            $supervisorEnd = CarbonImmutable::parse($round->supervisor_assess_end, $timezone)->startOfDay();

            if ($selfEnd->lt($selfStart) || $supervisorEnd->lt($selfEnd)) {
                $issues->push([
                    'key' => 'date_order',
                    'count' => 1,
                    'message' => 'วันเริ่มประเมิน วันสิ้นสุดการประเมิน และวันสิ้นสุดการตรวจของหัวหน้าต้องเรียงตามลำดับ',
                ]);
            }
        }

        return [
            'ready' => $issues->isEmpty(),
            'issues' => $issues->values()->all(),
        ];
    }

    public function assertReady(int $roundId): void
    {
        $result = $this->check($roundId);

        if ($result['ready']) {
            return;
        }

        throw ValidationException::withMessages([
            'round' => collect($result['issues'])->pluck('message')->implode(' · '),
        ]);
    }
}
