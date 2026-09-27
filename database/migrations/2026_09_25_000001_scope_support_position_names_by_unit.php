<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ACADEMIC_UNIQUE_INDEX = 'positions_job_family_name_without_support_unit_unique';

    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            $table->dropUnique('positions_job_family_id_name_unique');
        });

        DB::statement(sprintf(
            'CREATE UNIQUE INDEX %s ON positions (job_family_id, name) WHERE support_unit_id IS NULL',
            self::ACADEMIC_UNIQUE_INDEX,
        ));
    }

    public function down(): void
    {
        DB::statement('DROP INDEX '.self::ACADEMIC_UNIQUE_INDEX);

        Schema::table('positions', function (Blueprint $table) {
            $table->unique(['job_family_id', 'name']);
        });
    }
};
