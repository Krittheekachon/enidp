<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('sso')->nullable()->unique()->after('id');
            $table->string('title')->nullable()->after('name');
            $table->string('first_name_th')->nullable()->after('title');
            $table->string('last_name_th')->nullable()->after('first_name_th');
            $table->string('first_name_en')->nullable()->after('last_name_th');
            $table->string('last_name_en')->nullable()->after('first_name_en');
            $table->string('phone', 12)->nullable()->after('email');
            $table->string('workline')->nullable()->after('phone');
            $table->string('department')->nullable()->after('workline');
            $table->string('position')->nullable()->after('department');
            $table->string('level')->nullable()->after('position');
            $table->string('role_key')->default('employee')->after('role_id');
            $table->string('supervisor')->nullable()->after('role_key');
            $table->string('evaluator2')->nullable()->after('supervisor');
            $table->boolean('is_active')->default(true)->after('evaluator2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'sso',
                'title',
                'first_name_th',
                'last_name_th',
                'first_name_en',
                'last_name_en',
                'phone',
                'workline',
                'department',
                'position',
                'level',
                'role_key',
                'supervisor',
                'evaluator2',
                'is_active',
            ]);
        });
    }
};
