<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $employeeLevelId = DB::table('levels')
            ->where('name', 'รองศาสตราจารย์')
            ->value('id');

        $users = [
            ['name' => 'Admin User', 'email' => 'admin@test.com', 'role' => 'admin'],
            ['name' => 'HR User', 'email' => 'hr@test.com', 'role' => 'hr'],
            ['name' => 'Supervisor User', 'email' => 'supervisor@test.com', 'role' => 'supervisor'],
            ['name' => 'Department Head User', 'email' => 'dept_head@test.com', 'role' => 'dept_head'],
            [
                'name' => 'Employee User',
                'email' => 'employee@test.com',
                'role' => 'employee',
                'workline' => 'วิชาการ',
                'level' => 'รองศาสตราจารย์',
                'level_id' => $employeeLevelId,
            ],
            ['name' => 'Dean User', 'email' => 'dean@test.com', 'role' => 'dean'],
        ];

        foreach ($users as $user) {
            $roleId = DB::table('roles')->where('key', $user['role'])->value('id');
            $attributes = [
                'name' => $user['name'],
                'password' => Hash::make('password'),
                'role_id' => $roleId,
            ];

            foreach (['workline', 'level', 'level_id'] as $field) {
                if (array_key_exists($field, $user)) {
                    $attributes[$field] = $user[$field];
                }
            }

            User::updateOrCreate(
                ['email' => $user['email']],
                $attributes,
            );
        }
    }
}
