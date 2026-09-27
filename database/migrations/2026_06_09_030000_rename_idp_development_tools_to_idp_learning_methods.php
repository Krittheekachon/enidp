<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('idp_development_tools')) {
            DB::table('idp_development_tools')->where('focus_type', 'formal')->delete();

            if (!Schema::hasTable('idp_learning_methods')) {
                Schema::rename('idp_development_tools', 'idp_learning_methods');
            }
        }

        if (Schema::hasTable('idp_learning_methods')) {
            DB::table('idp_learning_methods')->where('focus_type', 'formal')->delete();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('idp_learning_methods') && !Schema::hasTable('idp_development_tools')) {
            Schema::rename('idp_learning_methods', 'idp_development_tools');
        }
    }
};
