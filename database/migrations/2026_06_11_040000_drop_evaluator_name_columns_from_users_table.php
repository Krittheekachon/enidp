<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = collect(['supervisor', 'evaluator2', 'evaluator3'])
            ->filter(fn (string $column) => Schema::hasColumn('users', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'supervisor')) {
                $table->string('supervisor')->nullable();
            }

            if (! Schema::hasColumn('users', 'evaluator2')) {
                $table->string('evaluator2')->nullable();
            }

            if (! Schema::hasColumn('users', 'evaluator3')) {
                $table->string('evaluator3')->nullable();
            }
        });
    }
};
