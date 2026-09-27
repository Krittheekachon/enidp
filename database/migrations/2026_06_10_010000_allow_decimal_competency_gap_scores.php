<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE competency_gaps ALTER COLUMN actual_level TYPE numeric(5, 2) USING actual_level::numeric');
        DB::statement('ALTER TABLE competency_gaps ALTER COLUMN gap TYPE numeric(6, 2) USING gap::numeric');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE competency_gaps ALTER COLUMN actual_level TYPE smallint USING ROUND(actual_level)::smallint');
        DB::statement('ALTER TABLE competency_gaps ALTER COLUMN gap TYPE integer USING ROUND(gap)::integer');
    }
};
