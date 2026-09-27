<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('status')->default('nothing');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_comment')->nullable();
            $table->foreignId('approved_by_1')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_2')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('idp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idp_id')->constrained('idps')->cascadeOnDelete();
            $table->foreignId('competency_gap_id')->nullable()->constrained('competency_gaps')->nullOnDelete();
            $table->text('goal')->nullable();
            $table->unsignedTinyInteger('target_level')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('learning_method_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('learning_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('method_type_id')->nullable()->constrained('learning_method_types')->nullOnDelete();
            $table->string('provider')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('idp_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idp_item_id')->constrained('idp_items')->cascadeOnDelete();
            $table->foreignId('learning_catalog_id')->nullable()->constrained('learning_catalogs')->nullOnDelete();
            $table->foreignId('method_type_id')->nullable()->constrained('learning_method_types')->nullOnDelete();
            $table->string('activity_name')->nullable();
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('planned');
            $table->string('result')->default('pending');
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('idp_activity_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('idp_activities')->cascadeOnDelete();
            $table->text('progress_note')->nullable();
            $table->unsignedTinyInteger('percent_complete')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_url')->nullable();
            $table->string('evidence_description')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('saved');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_activity_updates');
        Schema::dropIfExists('idp_activities');
        Schema::dropIfExists('learning_catalogs');
        Schema::dropIfExists('learning_method_types');
        Schema::dropIfExists('idp_items');
        Schema::dropIfExists('idps');
    }
};
