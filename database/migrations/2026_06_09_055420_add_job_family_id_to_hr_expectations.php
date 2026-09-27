<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('hr_expectations', function (Blueprint $table) {
            $table->foreignId('job_family_id')
                ->nullable()
                ->after('level_id')
                ->constrained('job_families')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('hr_expectations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_family_id');
        });
    }
};
