<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'key')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('key')->nullable()->after('id')->unique();
            });
        }

        DB::table('roles')->whereNull('key')->update([
            'key' => DB::raw('role_key'),
        ]);

        $roles = DB::table('roles')->get(['id', 'role_id', 'role_key', 'key']);
        $employeeRoleId = (int) ($roles->firstWhere('key', 'employee')->id ?? $roles->firstWhere('role_key', 'employee')->id ?? 1);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_id_foreign');
        }

        foreach ($roles as $role) {
            DB::table('users')
                ->when(Schema::hasColumn('users', 'role_key'), function ($query) use ($role) {
                    $query->where('role_key', $role->role_key);
                })
                ->orWhere('role_id', $role->role_id)
                ->update(['role_id' => -1 * (int) $role->id]);
        }

        DB::table('users')->where('role_id', '>', 0)->update(['role_id' => -1 * $employeeRoleId]);
        DB::table('users')->where('role_id', '<', 0)->update(['role_id' => DB::raw('role_id * -1')]);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('role_id', 'users_role_id_foreign')
                    ->references('id')
                    ->on('roles')
                    ->cascadeOnUpdate()
                    ->restrictOnDelete();
            });

            if (Schema::hasColumn('users', 'role_key')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropColumn('role_key');
                });
            }

            if (Schema::hasColumn('roles', 'role_id')) {
                Schema::table('roles', function (Blueprint $table) {
                    $table->dropColumn('role_id');
                });
            }

            if (Schema::hasColumn('roles', 'role_key')) {
                Schema::table('roles', function (Blueprint $table) {
                    $table->dropColumn('role_key');
                });
            }
        }
    }

    public function down(): void
    {
        //
    }
};
