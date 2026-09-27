<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'assessment_round_id')) {
            Schema::table('assessments', function (Blueprint $table): void {
                $table->foreignId('assessment_round_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('assessment_rounds')
                    ->nullOnDelete();
            });
        }

        $roundId = DB::table('assessment_rounds')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->value('id')
            ?: DB::table('assessment_rounds')->orderByDesc('year')->orderByDesc('id')->value('id');

        if (! $roundId && DB::table('assessments')->exists()) {
            $roundId = DB::table('assessment_rounds')->insertGetId([
                'name' => 'ข้อมูลการประเมินเดิม',
                'year' => (int) now()->format('Y') + 543,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($roundId) {
            DB::table('assessments')
                ->whereNull('assessment_round_id')
                ->update(['assessment_round_id' => $roundId]);
        }

        DB::statement('DROP INDEX IF EXISTS assessments_user_competency_unique');
        DB::statement('DROP INDEX IF EXISTS assessments_round_user_competency_unique');
        DB::statement('CREATE UNIQUE INDEX assessments_round_user_competency_unique ON assessments (assessment_round_id, user_id, competency_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS assessments_round_user_competency_unique');

        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $orderBy = $isSqlite ? 'updated_at DESC, id DESC' : 'updated_at DESC NULLS LAST, id DESC';
        DB::statement(<<<SQL
            DELETE FROM assessments
            WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY user_id, competency_id ORDER BY {$orderBy}
                    ) AS row_number
                    FROM assessments
                ) duplicates
                WHERE row_number > 1
            )
        SQL);

        DB::statement('CREATE UNIQUE INDEX assessments_user_competency_unique ON assessments (user_id, competency_id)');

        if (Schema::hasColumn('assessments', 'assessment_round_id')) {
            Schema::table('assessments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('assessment_round_id');
            });
        }
    }
};
