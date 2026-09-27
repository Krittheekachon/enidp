<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->foreignId('support_unit_id')
                ->nullable()
                ->after('job_family_id')
                ->constrained('support_units')
                ->nullOnDelete();
            $table->unique(['support_unit_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique(['support_unit_id', 'name']);
            $table->dropConstrainedForeignId('support_unit_id');
        });
    }
};
