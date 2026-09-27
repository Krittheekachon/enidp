<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('evaluator3')->nullable()->after('evaluator2');
            $table->foreignId('supervisor_id_3')->nullable()->after('supervisor_id_2')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id_3');
            $table->dropColumn('evaluator3');
        });
    }
};
