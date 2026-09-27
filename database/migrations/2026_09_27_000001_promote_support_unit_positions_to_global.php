<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('positions') || ! Schema::hasColumn('positions', 'support_unit_id')) {
            return;
        }

        $supportWorklineIds = DB::table('worklines')
            ->whereIn('name', ['สายสนับสนุน', 'สายงานสนับสนุน'])
            ->pluck('id');

        if ($supportWorklineIds->isEmpty()) {
            return;
        }

        $supportJobFamilyIds = DB::table('job_families')
            ->whereIn('workline_id', $supportWorklineIds)
            ->pluck('id');

        if ($supportJobFamilyIds->isEmpty()) {
            return;
        }

        $unitPositions = DB::table('positions')
            ->whereIn('job_family_id', $supportJobFamilyIds)
            ->whereNotNull('support_unit_id')
            ->select('id', 'job_family_id', 'name')
            ->orderBy('id')
            ->get();

        foreach ($unitPositions as $unitPosition) {
            $globalPositionId = DB::table('positions')
                ->where('job_family_id', $unitPosition->job_family_id)
                ->whereNull('support_unit_id')
                ->where('name', $unitPosition->name)
                ->value('id');

            if (! $globalPositionId) {
                $globalPositionId = DB::table('positions')->insertGetId([
                    'job_family_id' => $unitPosition->job_family_id,
                    'support_unit_id' => null,
                    'name' => $unitPosition->name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $globalPositionId = (int) $globalPositionId;

            if (Schema::hasTable('users') && Schema::hasColumn('users', 'position_id')) {
                DB::table('users')
                    ->where('position_id', $unitPosition->id)
                    ->update([
                        'position_id' => $globalPositionId,
                        'updated_at' => now(),
                    ]);
            }

            $this->copyPositionCompetencies((int) $unitPosition->id, $globalPositionId);
            $this->copyFcSelectionRules((int) $unitPosition->id, $globalPositionId);
        }
    }

    public function down(): void
    {
        // Keep promoted global positions and remapped users. This migration normalizes live master data.
    }

    private function copyPositionCompetencies(int $sourcePositionId, int $targetPositionId): void
    {
        if (! Schema::hasTable('position_competencies')) {
            return;
        }

        $hasRound = Schema::hasColumn('position_competencies', 'assessment_round_id');
        $rows = DB::table('position_competencies')
            ->where('position_id', $sourcePositionId)
            ->get();

        foreach ($rows as $row) {
            $payload = [
                'position_id' => $targetPositionId,
                'competency_id' => $row->competency_id,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($hasRound) {
                $payload['assessment_round_id'] = $row->assessment_round_id;
            }

            DB::table('position_competencies')->insertOrIgnore($payload);
        }
    }

    private function copyFcSelectionRules(int $sourcePositionId, int $targetPositionId): void
    {
        if (! Schema::hasTable('position_fc_selection_rules')) {
            return;
        }

        $hasRound = Schema::hasColumn('position_fc_selection_rules', 'assessment_round_id');
        $rows = DB::table('position_fc_selection_rules')
            ->where('position_id', $sourcePositionId)
            ->get();

        foreach ($rows as $row) {
            $payload = [
                'position_id' => $targetPositionId,
                'required_fc_count' => $row->required_fc_count,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($hasRound) {
                $payload['assessment_round_id'] = $row->assessment_round_id;
            }

            DB::table('position_fc_selection_rules')->insertOrIgnore($payload);
        }
    }
};
