<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MISSING_REVIEWER_COMMENT = 'ยังไม่ได้กำหนดผู้อนุมัติแผน IDP';

    public function up(): void
    {
        Schema::table('idp_items', function (Blueprint $table): void {
            $table->unsignedInteger('submission_version')->default(0)->after('status');
            $table->unsignedTinyInteger('current_review_step')->nullable()->after('submission_version');
        });

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

        DB::table('idp_items')
            ->join('idps', 'idp_items.idp_id', '=', 'idps.id')
            ->join('users', 'idps.user_id', '=', 'users.id')
            ->whereIn('idp_items.status', ['submitted', 'approved'])
            ->select([
                'idp_items.id as item_id',
                'idp_items.status',
                'users.supervisor_id_1',
                'users.supervisor_id_2',
                'users.supervisor_id_3',
            ])
            ->chunkById(200, function ($items): void {
                foreach ($items as $item) {
                    if ($item->status === 'approved') {
                        DB::table('idp_items')
                            ->where('id', $item->item_id)
                            ->update([
                                'submission_version' => 1,
                                'current_review_step' => null,
                            ]);

                        continue;
                    }

                    $firstStep = collect([1, 2, 3])->first(
                        fn (int $step): bool => filled($item->{'supervisor_id_'.$step})
                    );

                    DB::table('idp_items')
                        ->where('id', $item->item_id)
                        ->update([
                            'status' => $firstStep ? 'review_step_'.$firstStep : 'revision_required',
                            'submission_version' => 1,
                            'current_review_step' => $firstStep,
                            'reject_comment' => $firstStep
                                ? null
                                : self::MISSING_REVIEWER_COMMENT,
                        ]);
                }
            }, 'idp_items.id', 'item_id');
    }

    public function down(): void
    {
        DB::table('idp_items')
            ->whereIn('status', ['review_step_1', 'review_step_2', 'review_step_3'])
            ->update(['status' => 'submitted']);

        DB::table('idp_items')
            ->where('status', 'revision_required')
            ->where('submission_version', 1)
            ->whereNull('current_review_step')
            ->where('reject_comment', self::MISSING_REVIEWER_COMMENT)
            ->update([
                'status' => 'submitted',
                'reject_comment' => null,
            ]);

        Schema::dropIfExists('idp_item_reviews');

        Schema::table('idp_items', function (Blueprint $table): void {
            $table->dropColumn(['submission_version', 'current_review_step']);
        });
    }
};
