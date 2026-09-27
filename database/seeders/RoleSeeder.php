<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RoleSeeder extends Seeder
{
    // database/seeders/RoleSeeder.php
    public function run(): void
    {
        $roles = [
            ['key' => 'admin',      'name_th' => 'ผู้ดูแลระบบ',      'name_en' => 'Admin'],
            ['key' => 'supervisor', 'name_th' => 'หัวหน้าหน่วย',        'name_en' => 'Supervisor'],
            ['key' => 'dept_head',  'name_th' => 'หัวหน้างาน',    'name_en' => 'Department Head'],
            ['key' => 'division_head', 'name_th' => 'หัวหน้าฝ่าย', 'name_en' => 'Division Head'],
            ['key' => 'academic_department_head', 'name_th' => 'หัวหน้าภาควิชา', 'name_en' => 'Academic Department Head'],
            ['key' => 'employee',   'name_th' => 'บุคลากร',           'name_en' => 'Employee'],
            ['key' => 'hr',         'name_th' => 'งานทรัพยากรบุคคล', 'name_en' => 'HR'],
            ['key' => 'dean',       'name_th' => 'ผู้บริหารคณะ',      'name_en' => 'Dean'],
        ];

        foreach ($roles as $role) {
            $values = $role;

            if (Schema::hasColumn('roles', 'role_key')) {
                $values['role_key'] = $role['key'];
            }

            if (Schema::hasColumn('roles', 'role_id')) {
                $existingRoleId = DB::table('roles')->where('key', $role['key'])->value('role_id');
                $values['role_id'] = $existingRoleId ?? (((int) DB::table('roles')->max('role_id')) + 1);
            }

            DB::table('roles')->updateOrInsert(['key' => $role['key']], $values);
        }
    }
}
