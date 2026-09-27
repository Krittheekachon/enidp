<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competency_types', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('description');
            $table->unsignedTinyInteger('sort_order')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('competency_types', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'sort_order']);
        });
    }
};
