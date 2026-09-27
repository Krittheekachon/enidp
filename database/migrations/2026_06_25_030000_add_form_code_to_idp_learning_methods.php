<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idp_learning_methods')) {
            return;
        }

        if (! Schema::hasColumn('idp_learning_methods', 'form_code')) {
            Schema::table('idp_learning_methods', function (Blueprint $table) {
                $table->string('form_code', 80)->nullable()->after('title');
            });
        }

        $formCodesByCode = [
            '01' => 'form_3_project_assignment',
            '02' => 'form_4_ojt',
            '03' => 'form_5_coaching',
            '04' => 'form_6_mentoring',
            '05' => 'form_7_group_activity',
            '06' => 'form_8_feedback',
            '07' => 'form_9_field_trip',
        ];

        DB::table('idp_learning_methods')
            ->select('id', 'code')
            ->orderBy('id')
            ->get()
            ->each(function (object $method) use ($formCodesByCode): void {
                $code = preg_replace('/\D+/', '', (string) ($method->code ?? ''));
                $code = $code === '' ? '' : str_pad(substr($code, -2), 2, '0', STR_PAD_LEFT);

                DB::table('idp_learning_methods')
                    ->where('id', $method->id)
                    ->update(['form_code' => $formCodesByCode[$code] ?? null]);
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('idp_learning_methods') && Schema::hasColumn('idp_learning_methods', 'form_code')) {
            Schema::table('idp_learning_methods', function (Blueprint $table) {
                $table->dropColumn('form_code');
            });
        }
    }
};
