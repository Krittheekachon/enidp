<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_fc_selection_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->unique()->constrained('positions')->cascadeOnDelete();
            $table->unsignedSmallInteger('required_fc_count')->default(0);
            $table->timestamps();
        });

        Schema::create('fc_topic_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->string('status')->default('draft');
            $table->foreignId('submitted_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'position_id']);
        });

        Schema::create('fc_topic_selection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fc_topic_selection_id')->constrained('fc_topic_selections')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fc_topic_selection_id', 'competency_id'], 'fc_selection_item_unique');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE position_fc_selection_rules ADD CONSTRAINT position_fc_selection_required_count_nonnegative CHECK (required_fc_count >= 0)");
            DB::statement("ALTER TABLE fc_topic_selections ADD CONSTRAINT fc_topic_selections_status_check CHECK (status IN ('draft', 'submitted', 'approved', 'revision_required'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fc_topic_selection_items');
        Schema::dropIfExists('fc_topic_selections');
        Schema::dropIfExists('position_fc_selection_rules');
    }
};
