<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_type_id')->constrained('competency_types')->restrictOnDelete();
            $table->string('code')->unique();
            $table->text('name');
            $table->text('detail')->nullable();
            $table->timestamps();
        });

        Schema::create('competency_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['competency_id', 'level']);
        });

        Schema::create('comp_level_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competency_level_id')->constrained('competency_levels')->cascadeOnDelete();
            $table->text('description');
            $table->decimal('weight', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('assessment_rounds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('year');
            $table->date('self_assess_start')->nullable();
            $table->date('self_assess_end')->nullable();
            $table->date('supervisor_assess_end')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('hr_expectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_round_id')->constrained('assessment_rounds')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('levels')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->unsignedTinyInteger('expected_level')->nullable();
            $table->timestamps();

            $table->unique(['assessment_round_id', 'position_id', 'level_id', 'competency_id'], 'hr_expectations_unique_scope');
        });

        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_round_id')->constrained('assessment_rounds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('last_draft_saved_at')->nullable();
            $table->timestamp('self_submitted_at')->nullable();
            $table->timestamp('supervisor_1_submitted_at')->nullable();
            $table->timestamp('supervisor_2_submitted_at')->nullable();
            $table->timestamp('dean_approved_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_round_id', 'user_id']);
        });

        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->foreignId('assessor_id')->constrained('users')->cascadeOnDelete();
            $table->string('assessor_role');
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('comment')->nullable();
            $table->string('status')->default('draft');
            $table->timestamp('auto_saved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'competency_id', 'assessor_id']);
        });

        Schema::create('assessment_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('url')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('competency_gaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->foreignId('supervisor_2_score_id')->nullable()->constrained('scores')->nullOnDelete();
            $table->unsignedTinyInteger('expected_level')->nullable();
            $table->unsignedTinyInteger('actual_level')->nullable();
            $table->integer('gap')->nullable();
            $table->boolean('requires_idp')->default(false);
            $table->string('status')->default('draft');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reject_comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'competency_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competency_gaps');
        Schema::dropIfExists('assessment_evidences');
        Schema::dropIfExists('scores');
        Schema::dropIfExists('assessments');
        Schema::dropIfExists('hr_expectations');
        Schema::dropIfExists('assessment_rounds');
        Schema::dropIfExists('comp_level_indicators');
        Schema::dropIfExists('competency_levels');
        Schema::dropIfExists('competencies');
    }
};
