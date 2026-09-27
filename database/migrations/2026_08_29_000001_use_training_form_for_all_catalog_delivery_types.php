<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('learning_catalog_delivery_types')
            && Schema::hasColumn('learning_catalog_delivery_types', 'form_code')) {
            DB::table('learning_catalog_delivery_types')
                ->whereIn('key', ['in_class', 'e_learning'])
                ->update(['form_code' => 'form_10_training']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('learning_catalog_delivery_types')
            && Schema::hasColumn('learning_catalog_delivery_types', 'form_code')) {
            DB::table('learning_catalog_delivery_types')
                ->where('key', 'e_learning')
                ->update(['form_code' => 'form_9_field_trip']);
        }
    }
};
