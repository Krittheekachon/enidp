<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learning_catalogs', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('id');
            $table->string('source_type')->default('internal')->after('method_type_id');
            $table->decimal('hours', 6, 2)->nullable()->after('cost');
            $table->json('expected_levels')->nullable()->after('hours');
        });

        Schema::create('learning_catalog_competency', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_catalog_id')->constrained('learning_catalogs')->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competencies')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learning_catalog_id', 'competency_id'], 'learning_catalog_competency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_catalog_competency');

        Schema::table('learning_catalogs', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'source_type', 'hours', 'expected_levels']);
        });
    }
};
