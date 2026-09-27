<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $values = [
            'name_th' => 'หัวหน้าฝ่าย',
            'name_en' => 'Division Head',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // SQLite keeps the legacy columns in the compatibility migration.
        if (Schema::hasColumn('roles', 'role_key')) {
            $values['role_key'] = 'division_head';
        }

        if (Schema::hasColumn('roles', 'role_id')) {
            $values['role_id'] = ((int) DB::table('roles')->max('role_id')) + 1;
        }

        DB::table('roles')->updateOrInsert(
            ['key' => 'division_head'],
            $values,
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('key', 'division_head')->delete();
    }
};
