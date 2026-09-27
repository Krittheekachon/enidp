<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_departments')) {
            Schema::create('support_departments', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('support_works')) {
            Schema::create('support_works', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_department_id')->constrained('support_departments')->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['support_department_id', 'name']);
            });
        }

        if (!Schema::hasTable('support_units')) {
            Schema::create('support_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_work_id')->constrained('support_works')->cascadeOnDelete();
                $table->string('name');
                $table->timestamps();

                $table->unique(['support_work_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_units');
        Schema::dropIfExists('support_works');
        Schema::dropIfExists('support_departments');
    }
};
