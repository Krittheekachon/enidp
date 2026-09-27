<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reviewer_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'step_order']);
            $table->unique(['user_id', 'reviewer_id']);
        });

        $now = now();
        $rows = [];

        DB::table('users')
            ->select('id', 'supervisor_id_1', 'supervisor_id_2', 'supervisor_id_3')
            ->orderBy('id')
            ->chunk(500, function ($users) use (&$rows, $now): void {
                foreach ($users as $user) {
                    foreach ([1, 2, 3] as $step) {
                        $reviewerId = $user->{'supervisor_id_'.$step} ?? null;
                        if (! $reviewerId || (int) $reviewerId === (int) $user->id) {
                            continue;
                        }

                        $rows[] = [
                            'user_id' => $user->id,
                            'step_order' => $step,
                            'reviewer_id' => $reviewerId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            });

        if ($rows !== []) {
            DB::table('user_reviewer_steps')->insertOrIgnore($rows);
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_reviewer_steps ADD CONSTRAINT user_reviewer_steps_step_order_positive CHECK (step_order > 0)');
            DB::statement('ALTER TABLE user_reviewer_steps ADD CONSTRAINT user_reviewer_steps_not_self CHECK (user_id <> reviewer_id)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reviewer_steps');
    }
};
