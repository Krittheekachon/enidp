<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDashboardUserStructureSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_users_show_current_job_family_name_for_existing_assignments(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);

        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานใหม่',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'ZZ Existing User',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'กลุ่มงานเก่า > หน่วยเดิม',
            'position' => 'นักทรัพยากรบุคคล',
            'position_id' => $positionId,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', fn ($users): bool => collect($users)
                    ->firstWhere('n', 'ZZ Existing User')['d'] === 'กลุ่มงานใหม่ > หน่วยเดิม')
            );
    }

    public function test_dashboard_marks_users_with_deleted_structure_assignments(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);

        DB::table('worklines')->insert([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'ZZ Orphaned User',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'กลุ่มงานที่ถูกลบ',
            'position' => 'ตำแหน่งที่ถูกลบ',
            'level' => 'ระดับที่ถูกลบ',
            'position_id' => null,
            'level_id' => null,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Orphaned User');

                    return $user
                        && $user['structureStatus'] === 'invalid'
                        && ($user['structureIssues'][0] ?? null) === 'กลุ่มงานนี้ไม่มีในโครงสร้างปัจจุบัน'
                        && ($user['structureIssues'][1] ?? null) === 'ตำแหน่งนี้ไม่มีในกลุ่มงานปัจจุบัน'
                        && ($user['structureIssues'][2] ?? null) === 'ระดับตำแหน่งนี้ไม่มีในโครงสร้างปัจจุบัน';
                })
            );
    }

    public function test_dashboard_marks_users_without_any_evaluator_assignment(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);

        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'HR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => 'ปฏิบัติการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        User::factory()->create([
            'name' => 'ZZ No Evaluator User',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ No Evaluator User');

                    return $user
                        && $user['structureStatus'] === 'invalid'
                        && ($user['structureIssues'][0] ?? null) === 'ยังไม่ได้กำหนดลำดับการประเมิน'
                        && ($user['structureIssues'][1] ?? null) === 'ยังไม่ได้กำหนดลำดับ IDP';
                })
            );
    }

    public function test_dashboard_accepts_supervisor_with_only_third_evaluator(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);
        $this->createValidStructure();
        $dean = User::factory()->create([
            'name' => 'MM Dean Evaluator',
            'role_id' => $this->roleId('dean'),
        ]);

        $user = User::factory()->create([
            'name' => 'ZZ Supervisor Missing Evaluator',
            'role_id' => $this->roleId('supervisor'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);
        $this->assignReviewer($user, $dean, 3);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Supervisor Missing Evaluator');

                    return $user
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
            );
    }

    public function test_dashboard_accepts_manager_dept_with_only_third_evaluator(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);
        $this->createValidStructure();
        $dean = User::factory()->create([
            'name' => 'MM Dean Evaluator',
            'role_id' => $this->roleId('dean'),
        ]);

        $user = User::factory()->create([
            'name' => 'ZZ Manager Dept Missing Evaluator',
            'role_id' => $this->roleId('dept_head'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);
        $this->assignReviewer($user, $dean, 3);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Manager Dept Missing Evaluator');

                    return $user
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
            );
    }

    public function test_dashboard_accepts_any_active_user_as_configured_reviewer(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);
        $this->createValidStructure();

        $formerSupervisor = User::factory()->create([
            'name' => 'MM Former Supervisor',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);

        $user = User::factory()->create([
            'name' => 'ZZ Staff With Former Supervisor',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);
        $this->assignReviewer($user, $formerSupervisor);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Staff With Former Supervisor');

                    return $user
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
            );
    }

    public function test_dashboard_accepts_reviewer_role_without_fixed_step_mapping(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
        ]);
        $this->createValidStructure();

        $movedSupervisor = User::factory()->create([
            'name' => 'MM Moved Supervisor',
            'role_id' => $this->roleId('dept_head'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);

        $user = User::factory()->create([
            'name' => 'ZZ Staff With Moved Supervisor',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'HR',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
        ]);
        $this->assignReviewer($user, $movedSupervisor);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Staff With Moved Supervisor');

                    return $user
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
            );
    }

    public function test_dashboard_preserves_support_department_work_and_unit_path(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
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
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => $unitId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('levels')->insertGetId([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => 'ปฏิบัติการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Support Reviewer',
            'role_id' => $this->roleId('supervisor'),
        ]);
        $user = User::factory()->create([
            'name' => 'ZZ Support Unit User',
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'ข้อมูลเก่าที่ไม่ควรแสดง',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ปฏิบัติการ',
            'position_id' => $positionId,
            'level_id' => $levelId,
        ]);
        $this->assignReviewer($user, $reviewer);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Support Unit User');

                    return $user
                        && $user['d'] === 'ฝ่ายบริหาร > งานบุคคล > หน่วยพัฒนา'
                        && $user['structureStatus'] === 'ok'
                        && $user['structureIssues'] === [];
                })
            );
    }

    public function test_dashboard_preserves_support_path_for_global_support_position(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
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
            'name' => 'ฝ่ายแผนยุทธศาสตร์และพัฒนาองค์กร',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $workId = DB::table('support_works')->insertGetId([
            'support_department_id' => $departmentId,
            'name' => 'งานแผนยุทธศาสตร์และทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('support_units')->insert([
            'support_work_id' => $workId,
            'name' => 'หน่วยแผนยุทธศาสตร์',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => null,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('levels')->insertGetId([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => 'ระดับชำนาญงาน / ชำนาญการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reviewer = User::factory()->create([
            'name' => 'Support Reviewer',
            'role_id' => $this->roleId('supervisor'),
        ]);
        $user = User::factory()->create([
            'name' => 'ZZ Global Support User',
            'role_id' => $this->roleId('hr'),
            'workline' => 'สายสนับสนุน',
            'department' => 'ฝ่ายแผนยุทธศาสตร์และพัฒนาองค์กร > งานแผนยุทธศาสตร์และทรัพยากรบุคคล > หน่วยแผนยุทธศาสตร์',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ระดับชำนาญงาน / ชำนาญการ',
            'position_id' => $positionId,
            'level_id' => $levelId,
        ]);
        $this->assignReviewer($user, $reviewer);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $user = collect($users)->firstWhere('n', 'ZZ Global Support User');

                    return $user
                        && $user['d'] === 'ฝ่ายแผนยุทธศาสตร์และพัฒนาองค์กร > งานแผนยุทธศาสตร์และทรัพยากรบุคคล > หน่วยแผนยุทธศาสตร์'
                        && $user['structureStatus'] === 'ok';
                })
            );
    }

    public function test_dashboard_accepts_support_heads_without_unit_when_path_is_valid(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'role_id' => $this->roleId('admin'),
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
            'name' => 'ฝ่ายแผนยุทธศาสตร์และพัฒนาองค์กร',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('support_works')->insert([
            'support_department_id' => $departmentId,
            'name' => 'งานแผนยุทธศาสตร์และทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => null,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('levels')->insertGetId([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => 'ระดับชำนาญงานพิเศษ / ชำนาญการพิเศษ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $reviewer = User::factory()->create([
            'name' => 'Support Reviewer',
            'role_id' => $this->roleId('supervisor'),
        ]);
        $divisionHead = User::factory()->create([
            'name' => 'ZZ Division Head Without Unit',
            'role_id' => $this->roleId('division_head'),
            'workline' => 'สายสนับสนุน',
            'department' => 'ฝ่ายแผนยุทธศาสตร์และพัฒนาองค์กร > งานแผนยุทธศาสตร์และทรัพยากรบุคคล',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ระดับชำนาญงานพิเศษ / ชำนาญการพิเศษ',
            'position_id' => $positionId,
            'level_id' => $levelId,
        ]);
        $deptHead = User::factory()->create([
            'name' => 'ZZ Dept Head Without Unit',
            'role_id' => $this->roleId('dept_head'),
            'workline' => 'สายสนับสนุน',
            'department' => 'ฝ่ายแผนยุทธศาสตร์และพัฒนาองค์กร > งานแผนยุทธศาสตร์และทรัพยากรบุคคล',
            'position' => 'นักทรัพยากรบุคคล',
            'level' => 'ระดับชำนาญงานพิเศษ / ชำนาญการพิเศษ',
            'position_id' => $positionId,
            'level_id' => $levelId,
        ]);
        $this->assignReviewer($divisionHead, $reviewer);
        $this->assignReviewer($deptHead, $reviewer);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('users', function ($users): bool {
                    $users = collect($users);
                    $divisionHead = $users->firstWhere('n', 'ZZ Division Head Without Unit');
                    $deptHead = $users->firstWhere('n', 'ZZ Dept Head Without Unit');

                    return $divisionHead
                        && $deptHead
                        && $divisionHead['structureStatus'] === 'ok'
                        && $deptHead['structureStatus'] === 'ok';
                })
            );
    }

    private function createValidStructure(): void
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'HR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => 'ปฏิบัติการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignReviewer(User $user, User $reviewer, int $step = 1): void
    {
        DB::table('user_reviewer_steps')->insert(collect(['assessment', 'idp'])->map(fn (string $chainType): array => [
            'user_id' => $user->id,
            'reviewer_id' => $reviewer->id,
            'step_order' => $step,
            'chain_type' => $chainType,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all());
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}
