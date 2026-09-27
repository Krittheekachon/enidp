<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('learning_catalogs') || !Schema::hasTable('learning_catalog_delivery_types')) {
            return;
        }

        DB::table('learning_catalog_delivery_types')->updateOrInsert(
            ['key' => 'e_learning'],
            [
                'code' => '09',
                'name_th' => 'การฝึกอบรมออนไลน์',
                'name_en' => 'e-Learning',
                'sort_order' => 1,
                'is_active' => true,
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
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        DB::table('learning_catalogs')
            ->whereNotIn('delivery_type', ['e_learning', 'in_class'])
            ->orWhereNull('delivery_type')
            ->update(['delivery_type' => 'e_learning']);

        Schema::table('learning_catalogs', function (Blueprint $table) {
            $table->foreign('delivery_type', 'learning_catalogs_delivery_type_foreign')
                ->references('key')
                ->on('learning_catalog_delivery_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('learning_catalogs')) {
            return;
        }

        Schema::table('learning_catalogs', function (Blueprint $table) {
            $table->dropForeign('learning_catalogs_delivery_type_foreign');
        });
    }
};
