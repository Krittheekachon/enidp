<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropUnique(['workline_id', 'name']);
            $table->foreignId('job_family_id')->nullable()->after('workline_id')->constrained('job_families')->nullOnDelete();
            $table->unique(['workline_id', 'job_family_id', 'name'], 'levels_workline_family_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('levels', function (Blueprint $table) {
            $table->dropUnique('levels_workline_family_name_unique');
            $table->dropConstrainedForeignId('job_family_id');
            $table->unique(['workline_id', 'name']);
        });
    }
};
