<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('idp_learning_methods') && ! Schema::hasColumn('idp_learning_methods', 'code')) {
            Schema::table('idp_learning_methods', function (Blueprint $table) {
                $table->string('code', 50)->nullable()->after('id');
            });

            $running = [];
            DB::table('idp_learning_methods')
                ->select('id', 'focus_type')
                ->orderBy('focus_type')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->each(function (object $method) use (&$running): void {
                    $focus = (string) $method->focus_type;
                    $running[$focus] = ($running[$focus] ?? 0) + 1;
                    $prefix = match ($focus) {
                        'social' => 'SOC',
                        'formal' => 'FOR',
                        default => 'EXP',
                    };

                    DB::table('idp_learning_methods')
                        ->where('id', $method->id)
                        ->whereNull('code')
                        ->update(['code' => sprintf('%s-%02d', $prefix, $running[$focus])]);
                });
        }

        if (Schema::hasTable('idp_activities') && ! Schema::hasColumn('idp_activities', 'idp_learning_method_id')) {
            Schema::table('idp_activities', function (Blueprint $table) {
                $table->foreignId('idp_learning_method_id')
                    ->nullable()
                    ->after('method_type_id')
                    ->constrained('idp_learning_methods')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('idp_activities') && Schema::hasColumn('idp_activities', 'idp_learning_method_id')) {
            Schema::table('idp_activities', function (Blueprint $table) {
                $table->dropConstrainedForeignId('idp_learning_method_id');
            });
        }

        if (Schema::hasTable('idp_learning_methods') && Schema::hasColumn('idp_learning_methods', 'code')) {
            Schema::table('idp_learning_methods', function (Blueprint $table) {
                $table->dropColumn('code');
            });
        }
    }
};
