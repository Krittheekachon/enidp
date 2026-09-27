<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviewer_chain_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reviewer_chain_template_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('reviewer_chain_templates')->cascadeOnDelete();
            $table->unsignedSmallInteger('step_order');
            $table->string('resolver_type', 40);
            $table->string('role_key', 80)->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['template_id', 'step_order']);
        });

        Schema::create('reviewer_chain_template_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('reviewer_chain_templates')->cascadeOnDelete();
            $table->string('scope_type', 40);
            $table->string('scope_value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_value']);
            $table->index(['scope_type', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('reviewer_template_id')
                ->nullable()
                ->after('supervisor_id_3')
                ->constrained('reviewer_chain_templates')
                ->nullOnDelete();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE reviewer_chain_template_steps ADD CONSTRAINT reviewer_chain_template_steps_resolver_type_check CHECK (resolver_type IN ('fixed_user', 'role_same_department', 'role_same_workline', 'role_any'))");
            DB::statement("ALTER TABLE reviewer_chain_template_assignments ADD CONSTRAINT reviewer_chain_template_assignments_scope_type_check CHECK (scope_type IN ('default', 'workline', 'job_family', 'position', 'user'))");
        }

        $templateId = DB::table('reviewer_chain_templates')->insertGetId([
            'name' => 'ลำดับมาตรฐานตามโครงสร้าง',
            'description' => 'หัวหน้าหน่วยในกลุ่มงานเดียวกัน -> หัวหน้างานในสายงานเดียวกัน -> ผู้บริหารคณะ',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('reviewer_chain_template_steps')->insert([
            [
                'template_id' => $templateId,
                'step_order' => 1,
                'resolver_type' => 'role_same_department',
                'role_key' => 'supervisor',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'template_id' => $templateId,
                'step_order' => 2,
                'resolver_type' => 'role_same_workline',
                'role_key' => 'dept_head',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'template_id' => $templateId,
                'step_order' => 3,
                'resolver_type' => 'role_any',
                'role_key' => 'dean',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('reviewer_chain_template_assignments')->insert([
            'template_id' => $templateId,
            'scope_type' => 'default',
            'scope_value' => null,
            'user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewer_template_id');
        });

        Schema::dropIfExists('reviewer_chain_template_assignments');
        Schema::dropIfExists('reviewer_chain_template_steps');
        Schema::dropIfExists('reviewer_chain_templates');
    }
};
