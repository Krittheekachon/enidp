<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_activities', function (Blueprint $table): void {
            $table->string('document_reference_number', 255)
                ->nullable()
                ->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('idp_activities', function (Blueprint $table): void {
            $table->dropColumn('document_reference_number');
        });
    }
};
