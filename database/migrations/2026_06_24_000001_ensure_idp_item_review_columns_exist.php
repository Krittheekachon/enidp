<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('idp_items', 'submission_version')) {
                $table->unsignedInteger('submission_version')->default(0);
            }

            if (! Schema::hasColumn('idp_items', 'current_review_step')) {
                $table->unsignedTinyInteger('current_review_step')->nullable();
            }
        });

        if (! Schema::hasTable('idp_item_reviews')) {
            Schema::create('idp_item_reviews', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('idp_item_id')->constrained('idp_items')->cascadeOnDelete();
                $table->unsignedInteger('submission_version');
                $table->unsignedTinyInteger('review_step');
                $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
                $table->string('decision', 20);
                $table->text('comment')->nullable();
                $table->timestamp('decided_at');
                $table->timestamps();

                $table->unique(
                    ['idp_item_id', 'submission_version', 'review_step'],
                    'idp_item_reviews_item_version_step_unique'
                );
                $table->index(
                    ['reviewer_id', 'review_step', 'decision'],
                    'idp_item_reviews_reviewer_step_decision_index'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('idp_items', function (Blueprint $table): void {
            if (Schema::hasColumn('idp_items', 'current_review_step')) {
                $table->dropColumn('current_review_step');
            }

            if (Schema::hasColumn('idp_items', 'submission_version')) {
                $table->dropColumn('submission_version');
            }
        });
    }
};
