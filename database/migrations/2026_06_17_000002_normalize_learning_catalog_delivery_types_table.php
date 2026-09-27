<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyCodes = [];

        if (Schema::hasTable('idp_delivery_type_settings')) {
            $legacyCodes = DB::table('idp_delivery_type_settings')
                ->pluck('code', 'delivery_type')
                ->all();
        }

        if (!Schema::hasTable('learning_catalog_delivery_types')) {
            Schema::create('learning_catalog_delivery_types', function (Blueprint $table) {
                $table->id();
                $table->string('key', 30)->unique();
                $table->string('code', 20);
                $table->string('name_th');
                $table->string('name_en')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $deliveryTypes = [
            'e_learning' => [
                'code' => $legacyCodes['e_learning'] ?? '09',
                'name_th' => 'การฝึกอบรมออนไลน์',
                'name_en' => 'e-Learning',
                'sort_order' => 1,
            ],
            'in_class' => [
                'code' => $legacyCodes['in_class'] ?? '10',
                'name_th' => 'การฝึกอบรมในห้องเรียน',
                'name_en' => 'In Class Training',
                'sort_order' => 2,
            ],
        ];

        foreach ($deliveryTypes as $key => $data) {
            DB::table('learning_catalog_delivery_types')->updateOrInsert(
                ['key' => $key],
                [
                    ...$data,
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        Schema::dropIfExists('idp_delivery_type_settings');
    }

    public function down(): void
    {
        Schema::create('idp_delivery_type_settings', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_type')->unique();
            $table->string('code', 20);
            $table->timestamps();
        });

        if (Schema::hasTable('learning_catalog_delivery_types')) {
            $codes = DB::table('learning_catalog_delivery_types')
                ->pluck('code', 'key')
                ->all();

            foreach (['e_learning' => '09', 'in_class' => '10'] as $key => $fallbackCode) {
                DB::table('idp_delivery_type_settings')->insert([
                    'delivery_type' => $key,
                    'code' => $codes[$key] ?? $fallbackCode,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
};
