<?php

namespace Tests\Feature;

use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class DashboardQueryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_competency_levels_are_loaded_once_per_dashboard_request(): void
    {
        $competencyTypeId = DB::table('competency_types')->insertGetId([
            'code' => 'PERF',
            'full_name' => 'Performance Test',
            'description' => 'Performance Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $competencyIds = collect(['PERF-001', 'PERF-002'])
            ->map(fn (string $code): int => DB::table('competencies')->insertGetId([
                'competency_type_id' => $competencyTypeId,
                'code' => $code,
                'name' => $code,
                'created_at' => now(),
                'updated_at' => now(),
            ]));

        foreach ($competencyIds as $competencyId) {
            foreach ([1, 2] as $level) {
                $levelId = DB::table('competency_levels')->insertGetId([
                    'competency_id' => $competencyId,
                    'level' => $level,
                    'description' => "Level {$level}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('comp_level_indicators')->insert([
                    'competency_level_id' => $levelId,
                    'description' => "Indicator {$level}",
                    'weight' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $controller = app(DashboardController::class);
        $method = new ReflectionMethod($controller, 'competencyLevelsPayload');

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($competencyIds as $competencyId) {
            $levels = $method->invoke($controller, $competencyId);
            $this->assertCount(2, $levels);
        }

        $this->assertCount(2, DB::getQueryLog());
    }
}
