<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

        DB::table('learning_catalog_delivery_types')->insert([
            [
                'key' => 'e_learning',
                'code' => '09',
                'name_th' => 'การฝึกอบรมออนไลน์',
                'name_en' => 'e-Learning',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'in_class',
                'code' => '10',
                'name_th' => 'การฝึกอบรมในห้องเรียน',
                'name_en' => 'In Class Training',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_catalog_delivery_types');
    }
};
