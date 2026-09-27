<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Force all comment/reject_comment columns to TEXT so reviewer feedback is not capped at 255 chars.
     */
    public function up(): void
    {
        $columns = [
            'scores' => ['comment'],
            'competency_gaps' => ['reject_comment'],
            'idps' => ['reject_comment'],
            'idp_activities' => ['reject_comment'],
        ];

        foreach ($columns as $table => $names) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($names as $name) {
                if (! Schema::hasColumn($table, $name)) {
                    continue;
                }

                $this->changeColumnToText($table, $name);
            }
        }
    }

    public function down(): void
    {
        // Intentionally keep these columns as TEXT to avoid truncating existing long comments.
    }

    private function changeColumnToText(string $table, string $column): void
    {
        match (DB::getDriverName()) {
            'pgsql' => DB::statement(sprintf(
                'ALTER TABLE "%s" ALTER COLUMN "%s" TYPE TEXT',
                $table,
                $column
            )),
            'mysql', 'mariadb' => DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `%s` TEXT NULL',
                $table,
                $column
            )),
            default => null,
        };
    }
};
