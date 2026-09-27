<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE comp_level_indicators ALTER COLUMN description TYPE TEXT');
        DB::statement('ALTER TABLE scores ALTER COLUMN comment TYPE TEXT');
        DB::statement('ALTER TABLE assessment_evidences ALTER COLUMN description TYPE TEXT');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE comp_level_indicators ALTER COLUMN description TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE scores ALTER COLUMN comment TYPE VARCHAR(255)');
        DB::statement('ALTER TABLE assessment_evidences ALTER COLUMN description TYPE VARCHAR(255)');
    }
};
