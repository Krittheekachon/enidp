<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';

        if (! $isSqlite) {
            DB::statement('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS assessments_assessment_round_id_foreign');
            DB::statement('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS assessments_assessment_round_id_user_id_unique');
            DB::statement('ALTER TABLE assessments DROP CONSTRAINT IF EXISTS assessments_round_user_competency_unique');
        }

        DB::statement('DROP INDEX IF EXISTS assessments_assessment_round_id_user_id_unique');
        DB::statement('DROP INDEX IF EXISTS assessments_round_user_competency_unique');

        $orderBy = $isSqlite ? 'updated_at DESC, id DESC' : 'updated_at DESC NULLS LAST, id DESC';

        DB::statement(<<<SQL
            DELETE FROM assessments
            WHERE id IN (
                SELECT id
                FROM (
                    SELECT
                        id,
                        ROW_NUMBER() OVER (
                            PARTITION BY user_id, competency_id
                            ORDER BY {$orderBy}
                        ) AS row_number
                    FROM assessments
                ) duplicates
                WHERE row_number > 1
            )
        SQL);

        if (! $isSqlite && Schema::hasColumn('assessments', 'assessment_round_id')) {
            Schema::table('assessments', function ($table): void {
                $table->dropColumn('assessment_round_id');
            });
        }

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS assessments_user_competency_unique ON assessments (user_id, competency_id)');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('assessments', 'assessment_round_id')) {
            Schema::table('assessments', function ($table): void {
                $table->foreignId('assessment_round_id')->nullable()->after('id')->constrained('assessment_rounds')->nullOnDelete();
            });
        }

        DB::statement('DROP INDEX IF EXISTS assessments_user_competency_unique');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS assessments_round_user_competency_unique ON assessments (assessment_round_id, user_id, competency_id)');
    }
};
