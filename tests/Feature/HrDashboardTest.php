<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HrDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_users_land_on_hr_dashboard_with_database_summary(): void
    {
        $hrUser = User::factory()->create([
            'email' => 'hr@example.com',
            'role_id' => $this->roleId('hr'),
        ]);

        User::factory()->create([
            'email' => 'staff@example.com',
            'role_id' => $this->roleId('employee'),
        ]);

        $this->actingAs($hrUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('hrSummary.totalUsers', 2)
                ->where('hrSummary.hrUsers', 1)
                ->where('hrSummary.employeeUsers', 1)
                ->where('hrSummary.source', 'database')
                ->missing('users')
                ->missing('overviewUsers')
                ->missing('roleSwitcher')
            );
    }

    public function test_hr_position_setup_uses_positions_not_levels_for_position_choices(): void
    {
        $hrUser = User::factory()->create([
            'email' => 'hr-position@example.com',
            'role_id' => $this->roleId('hr'),
        ]);

        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'name' => 'ตำแหน่งจากตาราง position',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'name' => 'ระดับที่ไม่ใช่ตำแหน่ง',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('jobFamiliesByWorkline.สายทดสอบ.กลุ่มงานทดสอบ.0', 'ตำแหน่งจากตาราง position')
                ->missing('levelsByWorkline')
            );
    }

    public function test_hr_dashboard_exposes_support_units_with_their_positions(): void
    {
        $hrUser = User::factory()->create([
            'role_id' => $this->roleId('hr'),
        ]);
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'ตำแหน่งสายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $departmentId = DB::table('support_departments')->insertGetId([
            'name' => 'ฝ่ายบริหาร',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $workId = DB::table('support_works')->insertGetId([
            'support_department_id' => $departmentId,
            'name' => 'งานบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unitId = DB::table('support_units')->insertGetId([
            'support_work_id' => $workId,
            'name' => 'หน่วยพัฒนา',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => $unitId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('supportOrg.ฝ่ายบริหาร.0.units.0.key', 'ฝ่ายบริหาร|||งานบุคคล|||หน่วยพัฒนา')
                ->where('supportOrg.ฝ่ายบริหาร.0.units.0.positions.0', 'นักทรัพยากรบุคคล')
                ->where('positionLookup.0.supportUnitKey', 'ฝ่ายบริหาร|||งานบุคคล|||หน่วยพัฒนา')
            );
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')
            ->where('key', $key)
            ->orWhere('role_key', $key)
            ->value('id');
    }
}
