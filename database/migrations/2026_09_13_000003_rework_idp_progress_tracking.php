<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_activity_updates', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique()->after('id');
            $table->unsignedSmallInteger('topic_index')->nullable()->after('form_data');
            $table->date('period_start')->nullable()->after('topic_index');
            $table->date('period_end')->nullable()->after('period_start');
            $table->foreignId('correction_of_id')->nullable()->after('period_end')
                ->constrained('idp_activity_updates')->nullOnDelete();
            $table->index(['activity_id', 'topic_index', 'status'], 'idp_updates_topic_status_idx');
        });

        DB::table('idp_activity_updates')->orderBy('id')->each(function (object $update): void {
            DB::table('idp_activity_updates')->where('id', $update->id)->update([
                'public_id' => (string) Str::uuid(),
                'topic_index' => 0,
            ]);
        });

        Schema::create('idp_activity_update_evidences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('activity_update_id')->constrained('idp_activity_updates')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('storage_path')->nullable();
            $table->text('url')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['activity_update_id', 'kind']);
        });

        Schema::create('idp_activity_topic_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('idp_activities')->cascadeOnDelete();
            $table->unsignedSmallInteger('topic_index');
            $table->string('result', 30);
            $table->text('reason')->nullable();
            $table->foreignId('finalized_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('finalized_at');
            $table->timestamps();
            $table->unique(['activity_id', 'topic_index']);
        });

        Schema::create('idp_item_completion_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('idp_item_id')->unique()->constrained('idp_items')->cascadeOnDelete();
            $table->unsignedInteger('submission_version')->default(0);
            $table->string('status', 40)->default('draft');
            $table->unsignedSmallInteger('current_review_step')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('idp_item_completion_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('completion_submission_id')->constrained('idp_item_completion_submissions')->cascadeOnDelete();
            $table->unsignedInteger('submission_version');
            $table->unsignedSmallInteger('review_step');
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 30);
            $table->text('comment')->nullable();
            $table->jsonb('review_data')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(
                ['completion_submission_id', 'submission_version', 'review_step'],
                'idp_completion_review_version_step_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_item_completion_reviews');
        Schema::dropIfExists('idp_item_completion_submissions');
        Schema::dropIfExists('idp_activity_topic_results');
        Schema::dropIfExists('idp_activity_update_evidences');

        Schema::table('idp_activity_updates', function (Blueprint $table): void {
            $table->dropForeign(['correction_of_id']);
            $table->dropIndex('idp_updates_topic_status_idx');
            $table->dropUnique(['public_id']);
            $table->dropColumn([
                'public_id',
                'topic_index',
                'period_start',
                'period_end',
                'correction_of_id',
            ]);
        });
    }
};
