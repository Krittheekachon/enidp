<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('assessment_indicator_results')) {
            Schema::create('assessment_indicator_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
                $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
                $table->string('indicator_key');
                $table->boolean('is_checked')->default(true);
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('checked_at')->nullable();
                $table->timestamps();

                $table->unique(['assessment_id', 'competency_id', 'indicator_key'], 'assessment_indicator_results_unique');
            });
        }

        if (Schema::hasTable('assessment_evidences') && Schema::hasColumn('assessment_evidences', 'indicator_key')) {
            DB::table('assessment_evidences')
                ->whereNotNull('indicator_key')
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        DB::table('assessment_indicator_results')->updateOrInsert(
                            [
                                'assessment_id' => $row->assessment_id,
                                'competency_id' => $row->competency_id,
                                'indicator_key' => $row->indicator_key,
                            ],
                            [
                                'is_checked' => true,
                                'checked_by' => $row->uploaded_by,
                                'checked_at' => $row->created_at,
                                'created_at' => $row->created_at,
                                'updated_at' => $row->updated_at,
                            ]
                        );
                    }
                });
        }
    }

    public function down(): void
    {
        // This migration repairs a table expected by older code, so rollback should not drop it.
    }
};
