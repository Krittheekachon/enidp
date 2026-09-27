<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropUnique('levels_name_unique');
            $table->foreignId('workline_id')->nullable()->after('id')->constrained('worklines')->nullOnDelete();
            $table->unique(['workline_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropUnique(['workline_id', 'name']);
            $table->dropConstrainedForeignId('workline_id');
            $table->unique('name');
        });
    }
};
