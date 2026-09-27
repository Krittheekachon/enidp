<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comp_level_indicators', function (Blueprint $table) {
            if (! Schema::hasColumn('comp_level_indicators', 'weight')) {
                $table->decimal('weight', 5, 2)->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comp_level_indicators', function (Blueprint $table) {
            if (Schema::hasColumn('comp_level_indicators', 'weight')) {
                $table->dropColumn('weight');
            }
        });
    }
};
