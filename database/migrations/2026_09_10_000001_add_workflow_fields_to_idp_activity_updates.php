<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_activity_updates', function (Blueprint $table): void {
            $table->string('form_code', 80)->nullable()->after('activity_id');
            $table->jsonb('form_data')->nullable()->after('form_code');
            $table->unsignedInteger('submission_version')->default(1)->after('form_data');
            $table->unsignedSmallInteger('current_review_step')->nullable()->after('submission_version');
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('submitted_at');
        });

        DB::table('idp_activity_updates')
            ->where('status', 'saved')
            ->update(['status' => 'draft']);

        Schema::create('idp_activity_update_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_update_id')->constrained('idp_activity_updates')->cascadeOnDelete();
            $table->unsignedInteger('submission_version');
            $table->unsignedSmallInteger('review_step');
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 30);
            $table->text('comment')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['activity_update_id', 'submission_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idp_activity_update_reviews');

        Schema::table('idp_activity_updates', function (Blueprint $table): void {
            $table->dropColumn([
                'form_code',
                'form_data',
                'submission_version',
                'current_review_step',
                'submitted_at',
                'approved_at',
            ]);
        });
    }
};
