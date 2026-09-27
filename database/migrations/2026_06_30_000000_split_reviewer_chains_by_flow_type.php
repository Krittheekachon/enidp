<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_reviewer_steps', function (Blueprint $table) {
            $table->string('chain_type', 30)->default('assessment')->after('user_id');
            $table->dropUnique(['user_id', 'step_order']);
            $table->dropUnique(['user_id', 'reviewer_id']);
            $table->unique(['user_id', 'chain_type', 'step_order']);
            $table->unique(['user_id', 'chain_type', 'reviewer_id']);
        });

        Schema::table('reviewer_chain_templates', function (Blueprint $table) {
            $table->string('chain_type', 30)->default('assessment')->after('description');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('idp_reviewer_template_id')
                ->nullable()
                ->after('reviewer_template_id')
                ->constrained('reviewer_chain_templates')
                ->nullOnDelete();
        });

        DB::table('reviewer_chain_templates')->update(['chain_type' => 'assessment']);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE user_reviewer_steps ADD CONSTRAINT user_reviewer_steps_chain_type_check CHECK (chain_type IN ('assessment', 'idp'))");
            DB::statement("ALTER TABLE reviewer_chain_templates ADD CONSTRAINT reviewer_chain_templates_chain_type_check CHECK (chain_type IN ('assessment', 'idp'))");
        }

        $assessmentDefault = DB::table('reviewer_chain_templates')
            ->where('chain_type', 'assessment')
            ->where('is_default', true)
            ->first();

        if ($assessmentDefault) {
            $idpTemplateId = DB::table('reviewer_chain_templates')->insertGetId([
                'name' => 'ลำดับ IDP มาตรฐานตามโครงสร้าง',
                'description' => 'ใช้ตอนบุคลากรส่งแผน IDP: หัวหน้าหน่วย -> หัวหน้างาน -> ผู้บริหารคณะ',
                'chain_type' => 'idp',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $steps = DB::table('reviewer_chain_template_steps')
                ->where('template_id', $assessmentDefault->id)
                ->orderBy('step_order')
                ->get(['step_order', 'resolver_type', 'role_key', 'reviewer_id'])
                ->map(fn (object $step): array => [
                    'template_id' => $idpTemplateId,
                    'step_order' => $step->step_order,
                    'resolver_type' => $step->resolver_type,
                    'role_key' => $step->role_key,
                    'reviewer_id' => $step->reviewer_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->all();

            if ($steps !== []) {
                DB::table('reviewer_chain_template_steps')->insert($steps);
            }

            DB::table('reviewer_chain_template_assignments')->insert([
                'template_id' => $idpTemplateId,
                'scope_type' => 'default',
                'scope_value' => null,
                'user_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('idp_reviewer_template_id');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE user_reviewer_steps DROP CONSTRAINT IF EXISTS user_reviewer_steps_chain_type_check');
            DB::statement('ALTER TABLE reviewer_chain_templates DROP CONSTRAINT IF EXISTS reviewer_chain_templates_chain_type_check');
        }

        DB::table('reviewer_chain_templates')->where('chain_type', 'idp')->delete();

        Schema::table('reviewer_chain_templates', function (Blueprint $table) {
            $table->dropColumn('chain_type');
        });

        Schema::table('user_reviewer_steps', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'chain_type', 'step_order']);
            $table->dropUnique(['user_id', 'chain_type', 'reviewer_id']);
            $table->unique(['user_id', 'step_order']);
            $table->unique(['user_id', 'reviewer_id']);
            $table->dropColumn('chain_type');
        });
    }
};
