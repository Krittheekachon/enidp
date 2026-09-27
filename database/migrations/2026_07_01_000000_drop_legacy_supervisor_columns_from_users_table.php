<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['supervisor_id_3', 'supervisor_id_2', 'supervisor_id_1'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'supervisor_id_1')) {
                $table->foreignId('supervisor_id_1')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'supervisor_id_2')) {
                $table->foreignId('supervisor_id_2')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'supervisor_id_3')) {
                $table->foreignId('supervisor_id_3')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }
};
