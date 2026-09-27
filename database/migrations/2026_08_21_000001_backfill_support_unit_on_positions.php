<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $assignments = DB::table('users')
            ->whereIn('workline', ['สายสนับสนุน', 'สายงานสนับสนุน'])
            ->whereNotNull('position_id')
            ->whereNotNull('department')
            ->select('position_id', 'department')
            ->distinct()
            ->get();

        foreach ($assignments as $assignment) {
            $path = array_values(array_filter(array_map('trim', explode(' > ', $assignment->department))));
            if (count($path) !== 3) {
                continue;
            }

            [$divisionName, $workName, $unitName] = $path;
            $unitIds = DB::table('support_units')
                ->join('support_works', 'support_units.support_work_id', '=', 'support_works.id')
                ->join('support_departments', 'support_works.support_department_id', '=', 'support_departments.id')
                ->where('support_departments.name', $divisionName)
                ->where('support_works.name', $workName)
                ->where('support_units.name', $unitName)
                ->pluck('support_units.id');

            if ($unitIds->count() === 1) {
                DB::table('positions')
                    ->where('id', $assignment->position_id)
                    ->whereNull('support_unit_id')
                    ->update(['support_unit_id' => $unitIds->first(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Keep resolved organization assignments when rolling back this data migration.
    }
};
