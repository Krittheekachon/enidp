<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_activity_update_reviews', function (Blueprint $table): void {
            $table->jsonb('review_data')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('idp_activity_update_reviews', function (Blueprint $table): void {
            $table->dropColumn('review_data');
        });
    }
};
