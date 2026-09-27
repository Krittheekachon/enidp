<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worklines', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('job_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workline_id')->nullable()->constrained('worklines')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['workline_id', 'name']);
        });

        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_family_id')->constrained('job_families')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['job_family_id', 'name']);
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('levels');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('job_families');
        Schema::dropIfExists('worklines');
    }
};
