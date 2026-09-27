<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_catalog_delivery_types')) {
            if (!Schema::hasColumn('learning_catalog_delivery_types', 'form_code')) {
                Schema::table('learning_catalog_delivery_types', function (Blueprint $table) {
                    $table->string('form_code', 80)->nullable()->after('code');
                });
            }

            DB::table('learning_catalog_delivery_types')->updateOrInsert(
                ['key' => 'e_learning'],
                [
                    'code' => '09',
                    'name_th' => 'การฝึกอบรมออนไลน์',
                    'name_en' => 'e-Learning',
                    'sort_order' => 1,
                    'is_active' => true,
                    'form_code' => 'form_9_field_trip',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            DB::table('learning_catalog_delivery_types')->updateOrInsert(
                ['key' => 'in_class'],
                [
                    'code' => '10',
                    'name_th' => 'การฝึกอบรมในห้องเรียน',
                    'name_en' => 'In Class Training',
                    'sort_order' => 2,
                    'is_active' => true,
                    'form_code' => 'form_10_training',
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        if (Schema::hasTable('learning_catalogs') && Schema::hasColumn('learning_catalogs', 'form_code')) {
            Schema::table('learning_catalogs', function (Blueprint $table) {
                $table->dropColumn('form_code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('learning_catalogs') && !Schema::hasColumn('learning_catalogs', 'form_code')) {
            Schema::table('learning_catalogs', function (Blueprint $table) {
                $table->string('form_code', 80)->nullable()->after('delivery_type');
            });
        }

        if (Schema::hasTable('learning_catalogs') && Schema::hasColumn('learning_catalogs', 'form_code')) {
            DB::table('learning_catalogs')
                ->whereNull('form_code')
                ->update(['form_code' => 'form_10_training']);
        }

        if (Schema::hasTable('learning_catalog_delivery_types') && Schema::hasColumn('learning_catalog_delivery_types', 'form_code')) {
            Schema::table('learning_catalog_delivery_types', function (Blueprint $table) {
                $table->dropColumn('form_code');
            });
        }
    }
};
