<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('idp_items', 'behavior_key')) {
                $table->string('behavior_key')->nullable()->after('competency_gap_id');
            }

            if (! Schema::hasColumn('idp_items', 'behavior_description')) {
                $table->text('behavior_description')->nullable()->after('behavior_key');
            }

            if (! Schema::hasColumn('idp_items', 'success_criteria')) {
                $table->text('success_criteria')->nullable()->after('goal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('idp_items', function (Blueprint $table): void {
            if (Schema::hasColumn('idp_items', 'success_criteria')) {
                $table->dropColumn('success_criteria');
            }

            if (Schema::hasColumn('idp_items', 'behavior_description')) {
                $table->dropColumn('behavior_description');
            }

            if (Schema::hasColumn('idp_items', 'behavior_key')) {
                $table->dropColumn('behavior_key');
            }
        });
    }
};
