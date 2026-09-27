<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idps', function (Blueprint $table): void {
            $table->foreignId('assessment_round_id')
                ->nullable()
                ->after('assessment_id')
                ->constrained('assessment_rounds')
                ->nullOnDelete();
        });

        DB::statement('UPDATE idps SET assessment_round_id = assessments.assessment_round_id FROM assessments WHERE idps.assessment_id = assessments.id AND idps.assessment_round_id IS NULL');
        DB::statement('UPDATE idps SET assessment_round_id = source.assessment_round_id FROM (SELECT idp_items.idp_id, MAX(assessments.assessment_round_id) AS assessment_round_id FROM idp_items JOIN competency_gaps ON competency_gaps.id = idp_items.competency_gap_id JOIN assessments ON assessments.id = competency_gaps.assessment_id WHERE assessments.assessment_round_id IS NOT NULL GROUP BY idp_items.idp_id) source WHERE idps.id = source.idp_id AND idps.assessment_round_id IS NULL');
        DB::statement('UPDATE idps SET assessment_round_id = assessment_rounds.id FROM assessment_rounds WHERE idps.assessment_round_id IS NULL AND assessment_rounds.year = idps.year AND assessment_rounds.id = (SELECT MAX(ar.id) FROM assessment_rounds ar WHERE ar.year = idps.year)');
    }

    public function down(): void
    {
        Schema::table('idps', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assessment_round_id');
        });
    }
};
