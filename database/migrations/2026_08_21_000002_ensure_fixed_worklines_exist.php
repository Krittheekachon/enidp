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

        foreach (['สายวิชาการ', 'สายสนับสนุน'] as $name) {
            DB::table('worklines')->updateOrInsert(
                ['name' => $name],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        // Fixed worklines may already own live organization data; keep them on rollback.
    }
};
