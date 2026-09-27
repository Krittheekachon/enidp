<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $roles = [
            ['role_id' => 0, 'role_key' => 'admin', 'name_th' => 'ผู้ดูแลระบบ', 'name_en' => 'Admin'],
            ['role_id' => 1, 'role_key' => 'supervisor', 'name_th' => 'หัวหน้าหน่วย', 'name_en' => 'Supervisor'],
            ['role_id' => 2, 'role_key' => 'dept_head', 'name_th' => 'หัวหน้างาน', 'name_en' => 'Department Head'],
            ['role_id' => 3, 'role_key' => 'employee', 'name_th' => 'บุคลากร', 'name_en' => 'Employee'],
            ['role_id' => 4, 'role_key' => 'hr', 'name_th' => 'งานทรัพยากรบุคคล', 'name_en' => 'HR'],
            ['role_id' => 5, 'role_key' => 'dean', 'name_th' => 'ผู้บริหารคณะ', 'name_en' => 'Dean'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_id' => $role['role_id']],
                [
                    ...$role,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        if (Schema::hasColumn('users', 'role_key')) {
            DB::table('users')->where('role_key', 'manager_dept')->update(['role_key' => 'dept_head', 'role_id' => 2]);
            DB::table('users')->where('role_key', 'manager')->update(['role_key' => 'dean', 'role_id' => 5]);

            foreach ($roles as $role) {
                DB::table('users')->where('role_id', $role['role_id'])->update(['role_key' => $role['role_key']]);
            }
        }
    }

    public function down(): void
    {
        //
    }
};
