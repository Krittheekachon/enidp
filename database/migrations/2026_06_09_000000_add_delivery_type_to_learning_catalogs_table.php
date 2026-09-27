<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_catalogs', function (Blueprint $table) {
            $table->string('delivery_type', 30)->default('e_learning')->after('method_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('learning_catalogs', function (Blueprint $table) {
            $table->dropColumn('delivery_type');
        });
    }
};
