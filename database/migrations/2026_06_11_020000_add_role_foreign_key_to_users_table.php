<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::table('users')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.role_id')
            ->whereNull('roles.role_id')
            ->update([
                'role_id' => 3,
                'role_key' => 'employee',
            ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id', 'users_role_id_foreign')
                ->references('role_id')
                ->on('roles')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_role_id_foreign');
        });
    }
};
