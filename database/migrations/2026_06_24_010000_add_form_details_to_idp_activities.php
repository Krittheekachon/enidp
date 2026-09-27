<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idp_activities', function (Blueprint $table): void {
            if (! Schema::hasColumn('idp_activities', 'form_code')) {
                $table->string('form_code')->nullable()->after('document_reference_number');
            }

            if (! Schema::hasColumn('idp_activities', 'form_details')) {
                $table->jsonb('form_details')->nullable()->after('form_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('idp_activities', function (Blueprint $table): void {
            if (Schema::hasColumn('idp_activities', 'form_details')) {
                $table->dropColumn('form_details');
            }

            if (Schema::hasColumn('idp_activities', 'form_code')) {
                $table->dropColumn('form_code');
            }
        });
    }
};
