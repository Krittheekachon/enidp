<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('job_families', function (Blueprint $table) {
            if ($this->constraintExists('job_families_name_unique')) {
                $table->dropUnique('job_families_name_unique');
            }

            if (!$this->constraintExists('job_families_workline_id_name_unique')) {
                $table->unique(['workline_id', 'name']);
            }
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('job_families', function (Blueprint $table) {
            if ($this->constraintExists('job_families_workline_id_name_unique')) {
                $table->dropUnique(['workline_id', 'name']);
            }

            if (!$this->constraintExists('job_families_name_unique')) {
                $table->unique('name');
            }
        });
    }

    private function constraintExists(string $name): bool
    {
        return DB::table('pg_constraint')->where('conname', $name)->exists();
    }
};
