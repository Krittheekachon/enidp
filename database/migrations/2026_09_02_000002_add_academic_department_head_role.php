<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $values = [
            'name_th' => 'หัวหน้าภาควิชา',
            'name_en' => 'Academic Department Head',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('roles', 'role_key')) {
            $values['role_key'] = 'academic_department_head';
        }

        if (Schema::hasColumn('roles', 'role_id')) {
            $values['role_id'] = ((int) DB::table('roles')->max('role_id')) + 1;
        }

        DB::table('roles')->updateOrInsert(
            ['key' => 'academic_department_head'],
            $values,
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('key', 'academic_department_head')->delete();
    }
};
