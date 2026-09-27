<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('assessments', 'competency_id')) {
                $table->foreignId('competency_id')->nullable()->after('user_id')->constrained('competencies')->cascadeOnDelete();
            }

            if (! Schema::hasColumn('assessments', 'score')) {
                $table->decimal('score', 4, 2)->default(0)->after('competency_id');
            }

            if (! Schema::hasColumn('assessments', 'note')) {
                $table->text('note')->nullable()->after('score');
            }
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropUnique(['assessment_round_id', 'user_id']);
            $table->unique(['assessment_round_id', 'user_id', 'competency_id'], 'assessments_round_user_competency_unique');
        });

        Schema::table('assessment_evidences', function (Blueprint $table) {
            if (! Schema::hasColumn('assessment_evidences', 'indicator_key')) {
                $table->string('indicator_key')->nullable()->after('uploaded_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assessment_evidences', function (Blueprint $table) {
            if (Schema::hasColumn('assessment_evidences', 'indicator_key')) {
                $table->dropColumn('indicator_key');
            }
        });

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropUnique('assessments_round_user_competency_unique');
            $table->unique(['assessment_round_id', 'user_id']);
        });

        Schema::table('assessments', function (Blueprint $table) {
            if (Schema::hasColumn('assessments', 'note')) {
                $table->dropColumn('note');
            }

            if (Schema::hasColumn('assessments', 'score')) {
                $table->dropColumn('score');
            }

            if (Schema::hasColumn('assessments', 'competency_id')) {
                $table->dropConstrainedForeignId('competency_id');
            }
        });
    }
};
