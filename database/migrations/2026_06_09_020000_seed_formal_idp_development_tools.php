<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Formal Learning is stored as real catalog rows in learning_catalogs.
    }

    public function down(): void
    {
        // No-op.
    }
};
