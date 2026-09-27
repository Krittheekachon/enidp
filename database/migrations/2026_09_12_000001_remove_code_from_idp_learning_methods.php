<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idp_learning_methods') || ! Schema::hasColumn('idp_learning_methods', 'code')) {
            return;
        }

        DB::table('idp_learning_methods')
            ->select('id', 'code')
            ->whereNotNull('code')
            ->orderBy('id')
            ->get()
            ->each(function (object $method): void {
                $prefix = trim((string) $method->code);
                if ($prefix === '') {
                    return;
                }

                DB::table('idp_activities')
                    ->select('id', 'activity_name')
                    ->where('idp_learning_method_id', $method->id)
                    ->where('activity_name', 'like', $prefix.' · %')
                    ->orderBy('id')
                    ->get()
                    ->each(function (object $activity) use ($prefix): void {
                        DB::table('idp_activities')
                            ->where('id', $activity->id)
                            ->update(['activity_name' => mb_substr((string) $activity->activity_name, mb_strlen($prefix.' · '))]);
                    });
            });

        Schema::table('idp_learning_methods', function (Blueprint $table): void {
            $table->dropColumn('code');
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('idp_learning_methods') && ! Schema::hasColumn('idp_learning_methods', 'code')) {
            Schema::table('idp_learning_methods', function (Blueprint $table): void {
                $table->string('code', 50)->nullable()->after('id');
            });
        }
    }
};
