<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->after('profile_photo')->constrained('positions')->nullOnDelete();
            $table->foreignId('level_id')->nullable()->after('position_id')->constrained('levels')->nullOnDelete();
            $table->foreignId('supervisor_id_1')->nullable()->after('level_id')->constrained('users')->nullOnDelete();
            $table->foreignId('supervisor_id_2')->nullable()->after('supervisor_id_1')->constrained('users')->nullOnDelete();
            $table->string('profile_affiliation')->nullable()->after('supervisor_id_2');
            $table->boolean('profile_saved')->default(false)->after('profile_affiliation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('level_id');
            $table->dropConstrainedForeignId('supervisor_id_1');
            $table->dropConstrainedForeignId('supervisor_id_2');
            $table->dropColumn(['profile_affiliation', 'profile_saved']);
        });
    }
};
