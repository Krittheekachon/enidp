<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminStructureControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_position_under_support_unit(): void
    {
        $admin = User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'admin')->value('id'),
        ]);
        $worklineId = DB::table('worklines')->insertGetId(['name' => 'สายสนับสนุน', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('job_families')->insert(['workline_id' => $worklineId, 'name' => 'ตำแหน่งสายสนับสนุน', 'created_at' => now(), 'updated_at' => now()]);
        $departmentId = DB::table('support_departments')->insertGetId(['name' => 'ฝ่ายบริหาร', 'created_at' => now(), 'updated_at' => now()]);
        $workId = DB::table('support_works')->insertGetId(['support_department_id' => $departmentId, 'name' => 'งานบุคคล', 'created_at' => now(), 'updated_at' => now()]);
        $unitId = DB::table('support_units')->insertGetId(['support_work_id' => $workId, 'name' => 'หน่วยพัฒนา', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)->post(route('admin.structure.positions.store'), [
            'division_name' => 'ฝ่ายบริหาร',
            'work_name' => 'งานบุคคล',
            'unit_name' => 'หน่วยพัฒนา',
            'name' => 'นักทรัพยากรบุคคล',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', [
            'support_unit_id' => $unitId,
            'name' => 'นักทรัพยากรบุคคล',
        ]);
    }

    public function test_admin_can_create_global_support_position_for_workline(): void
    {
        $admin = User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'admin')->value('id'),
        ]);
        $worklineId = DB::table('worklines')->insertGetId(['name' => 'สายสนับสนุน', 'created_at' => now(), 'updated_at' => now()]);
        $jobFamilyId = DB::table('job_families')->insertGetId(['workline_id' => $worklineId, 'name' => 'ตำแหน่งสายสนับสนุน', 'created_at' => now(), 'updated_at' => now()]);
        $departmentId = DB::table('support_departments')->insertGetId(['name' => 'ฝ่ายบริหาร', 'created_at' => now(), 'updated_at' => now()]);
        $workId = DB::table('support_works')->insertGetId(['support_department_id' => $departmentId, 'name' => 'งานบุคคล', 'created_at' => now(), 'updated_at' => now()]);
        $unitId = DB::table('support_units')->insertGetId(['support_work_id' => $workId, 'name' => 'หน่วยพัฒนา', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => $unitId,
            'name' => 'นักทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.structure.positions.store'), [
            'workline_name' => 'สายสนับสนุน',
            'job_family_name' => 'ตำแหน่งสายสนับสนุน',
            'name' => 'นักทรัพยากรบุคคล',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('positions', [
            'job_family_id' => $jobFamilyId,
            'support_unit_id' => null,
            'name' => 'นักทรัพยากรบุคคล',
        ]);
        $this->assertSame(2, DB::table('positions')->where('name', 'นักทรัพยากรบุคคล')->count());
    }

    public function test_support_position_name_can_repeat_in_different_units_but_not_the_same_unit(): void
    {
        $admin = User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'admin')->value('id'),
        ]);
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('job_families')->insert([
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
            'name' => 'งานเทคโนโลยี',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['หน่วยระบบ', 'หน่วยบริการ'] as $unitName) {
            DB::table('support_units')->insert([
                'support_work_id' => $workId,
                'name' => $unitName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs($admin)
                ->post(route('admin.structure.positions.store'), [
                    'division_name' => 'ฝ่ายบริหาร',
                    'work_name' => 'งานเทคโนโลยี',
                    'unit_name' => $unitName,
                    'name' => 'เจ้าหน้าที่ระบบงานคอมพิวเตอร์',
                ])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $systemUnitId = (int) DB::table('support_units')->where('name', 'หน่วยระบบ')->value('id');
        $serviceUnitId = (int) DB::table('support_units')->where('name', 'หน่วยบริการ')->value('id');
        $systemPositionId = (int) DB::table('positions')->where('support_unit_id', $systemUnitId)->value('id');
        $servicePositionId = (int) DB::table('positions')->where('support_unit_id', $serviceUnitId)->value('id');
        $systemUser = User::factory()->create([
            'workline' => 'สายสนับสนุน',
            'department' => 'ฝ่ายบริหาร > งานเทคโนโลยี > หน่วยระบบ',
            'position' => 'เจ้าหน้าที่ระบบงานคอมพิวเตอร์',
            'position_id' => $systemPositionId,
        ]);
        $serviceUser = User::factory()->create([
            'workline' => 'สายสนับสนุน',
            'department' => 'ฝ่ายบริหาร > งานเทคโนโลยี > หน่วยบริการ',
            'position' => 'เจ้าหน้าที่ระบบงานคอมพิวเตอร์',
            'position_id' => $servicePositionId,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.structure.positions.update'), [
                'division_name' => 'ฝ่ายบริหาร',
                'work_name' => 'งานเทคโนโลยี',
                'unit_name' => 'หน่วยระบบ',
                'old_name' => 'เจ้าหน้าที่ระบบงานคอมพิวเตอร์',
                'name' => 'เจ้าหน้าที่ระบบอาวุโส',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('เจ้าหน้าที่ระบบอาวุโส', $systemUser->refresh()->position);
        $this->assertSame('เจ้าหน้าที่ระบบงานคอมพิวเตอร์', $serviceUser->refresh()->position);

        $this->actingAs($admin)
            ->post(route('admin.structure.positions.store'), [
                'division_name' => 'ฝ่ายบริหาร',
                'work_name' => 'งานเทคโนโลยี',
                'unit_name' => 'หน่วยระบบ',
                'name' => 'เจ้าหน้าที่ระบบอาวุโส',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['name']);

        $this->assertSame(1, DB::table('positions')->where('name', 'เจ้าหน้าที่ระบบอาวุโส')->count());
        $this->assertSame(1, DB::table('positions')->where('name', 'เจ้าหน้าที่ระบบงานคอมพิวเตอร์')->count());
    }

    public function test_admin_can_create_update_and_delete_workline(): void
    {
        $admin = User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'admin')->value('id'),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.worklines.store'), ['name' => 'สายทดสอบ'])
            ->assertRedirect();

        $this->assertDatabaseHas('worklines', ['name' => 'สายทดสอบ']);

        $this->actingAs($admin)
            ->put(route('admin.structure.worklines.update'), [
                'old_name' => 'สายทดสอบ',
                'name' => 'สายทดสอบแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('worklines', ['name' => 'สายทดสอบแก้ไข']);
        $this->assertDatabaseMissing('worklines', ['name' => 'สายทดสอบ']);

        $this->actingAs($admin)
            ->delete(route('admin.structure.worklines.destroy'), ['name' => 'สายทดสอบแก้ไข'])
            ->assertRedirect();

        $this->assertDatabaseMissing('worklines', ['name' => 'สายทดสอบแก้ไข']);
    }

    public function test_admin_can_create_update_and_delete_job_family(): void
    {
        $admin = $this->createAdmin();
        DB::table('worklines')->insert(['id' => 1, 'name' => 'สายสนับสนุน', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($admin)
            ->post(route('admin.structure.job-families.store'), [
                'workline_name' => 'สายสนับสนุน',
                'name' => 'กลุ่มงานทดสอบ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('job_families', ['workline_id' => 1, 'name' => 'กลุ่มงานทดสอบ']);

        $this->actingAs($admin)
            ->put(route('admin.structure.job-families.update'), [
                'workline_name' => 'สายสนับสนุน',
                'old_name' => 'กลุ่มงานทดสอบ',
                'name' => 'กลุ่มงานแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('job_families', ['name' => 'กลุ่มงานแก้ไข']);
        $this->assertDatabaseMissing('job_families', ['name' => 'กลุ่มงานทดสอบ']);

        $this->actingAs($admin)
            ->delete(route('admin.structure.job-families.destroy'), [
                'workline_name' => 'สายสนับสนุน',
                'name' => 'กลุ่มงานแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('job_families', ['name' => 'กลุ่มงานแก้ไข']);
    }

    public function test_admin_can_reuse_job_family_name_in_different_worklines_but_not_same_workline(): void
    {
        $admin = $this->createAdmin();
        $academicId = DB::table('worklines')->insertGetId(['name' => 'วิชาการ', 'created_at' => now(), 'updated_at' => now()]);
        $supportId = DB::table('worklines')->insertGetId(['name' => 'สนับสนุน', 'created_at' => now(), 'updated_at' => now()]);

        DB::table('job_families')->insert([
            'workline_id' => $academicId,
            'name' => 'ทรัพยากรบุคคล',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.job-families.store'), [
                'workline_name' => 'สนับสนุน',
                'name' => 'ทรัพยากรบุคคล',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('job_families', [
            'workline_id' => $supportId,
            'name' => 'ทรัพยากรบุคคล',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.job-families.store'), [
                'workline_name' => 'วิชาการ',
                'name' => 'ทรัพยากรบุคคล',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['name']);

        $this->assertSame(1, DB::table('job_families')
            ->where('workline_id', $academicId)
            ->where('name', 'ทรัพยากรบุคคล')
            ->count());
    }

    public function test_admin_can_create_update_and_delete_position_under_job_family(): void
    {
        $admin = $this->createAdmin();
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.positions.store'), [
                'job_family_name' => 'กลุ่มงานทดสอบ',
                'name' => 'ตำแหน่งทดสอบ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('positions', ['job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งทดสอบ']);

        $this->actingAs($admin)
            ->put(route('admin.structure.positions.update'), [
                'job_family_name' => 'กลุ่มงานทดสอบ',
                'old_name' => 'ตำแหน่งทดสอบ',
                'name' => 'ตำแหน่งแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('positions', ['job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งแก้ไข']);
        $this->assertDatabaseMissing('positions', ['job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งทดสอบ']);

        $this->actingAs($admin)
            ->delete(route('admin.structure.positions.destroy'), [
                'job_family_name' => 'กลุ่มงานทดสอบ',
                'name' => 'ตำแหน่งแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('positions', ['job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งแก้ไข']);
    }

    public function test_admin_can_create_update_and_delete_level(): void
    {
        $admin = $this->createAdmin();
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายทดสอบ',
                'name' => 'ระดับทดสอบ',
                'expected_level' => 3,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('levels', [
            'workline_id' => $worklineId,
            'name' => 'ระดับทดสอบ',
            'expected_level' => 3,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.structure.levels.update'), [
                'workline_name' => 'สายทดสอบ',
                'old_name' => 'ระดับทดสอบ',
                'name' => 'ระดับแก้ไข',
                'expected_level' => 4,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('levels', [
            'workline_id' => $worklineId,
            'name' => 'ระดับแก้ไข',
            'expected_level' => 4,
        ]);
        $this->assertDatabaseMissing('levels', [
            'workline_id' => $worklineId,
            'name' => 'ระดับทดสอบ',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.structure.levels.destroy'), [
                'workline_name' => 'สายทดสอบ',
                'name' => 'ระดับแก้ไข',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('levels', [
            'workline_id' => $worklineId,
            'name' => 'ระดับแก้ไข',
        ]);
    }

    public function test_admin_can_store_level_without_expected_level_and_rejects_out_of_range_expected_level(): void
    {
        $admin = $this->createAdmin();
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายสนับสนุน',
                'name' => 'ปฏิบัติการ',
                'expected_level' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('levels', [
            'workline_id' => $worklineId,
            'name' => 'ปฏิบัติการ',
            'expected_level' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายสนับสนุน',
                'name' => 'ชำนาญการ',
                'expected_level' => 6,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['expected_level']);

        $this->assertDatabaseMissing('levels', [
            'workline_id' => $worklineId,
            'name' => 'ชำนาญการ',
        ]);
    }

    public function test_admin_cannot_scope_levels_to_a_job_family(): void
    {
        $admin = $this->createAdmin();
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายวิชาการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teacherFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'อาจารย์',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายวิชาการ',
                'job_family_name' => 'อาจารย์',
                'name' => 'ผศ',
                'expected_level' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['job_family_name']);

        $this->assertDatabaseMissing('levels', [
            'workline_id' => $worklineId,
            'job_family_id' => $teacherFamilyId,
            'name' => 'ผศ',
        ]);
    }

    public function test_renaming_structure_items_syncs_existing_user_assignment_strings(): void
    {
        $admin = $this->createAdmin();
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายเดิม',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มเดิม',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('positions')->insert([
            'job_family_id' => $jobFamilyId,
            'name' => 'ตำแหน่งเดิม',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('levels')->insert([
            'workline_id' => $worklineId,
            'job_family_id' => null,
            'name' => 'ระดับเดิม',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $user = User::factory()->create([
            'workline' => 'สายเดิม',
            'department' => 'กลุ่มเดิม > หน่วยเดิม',
            'position' => 'ตำแหน่งเดิม',
            'level' => 'ระดับเดิม',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.structure.worklines.update'), [
                'old_name' => 'สายเดิม',
                'name' => 'สายใหม่',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put(route('admin.structure.job-families.update'), [
                'workline_name' => 'สายใหม่',
                'old_name' => 'กลุ่มเดิม',
                'name' => 'กลุ่มใหม่',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put(route('admin.structure.positions.update'), [
                'workline_name' => 'สายใหม่',
                'job_family_name' => 'กลุ่มใหม่',
                'old_name' => 'ตำแหน่งเดิม',
                'name' => 'ตำแหน่งใหม่',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->put(route('admin.structure.levels.update'), [
                'workline_name' => 'สายใหม่',
                'old_name' => 'ระดับเดิม',
                'name' => 'ระดับใหม่',
                'expected_level' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'workline' => 'สายใหม่',
            'department' => 'กลุ่มใหม่ > หน่วยเดิม',
            'position' => 'ตำแหน่งใหม่',
            'level' => 'ระดับใหม่',
        ]);
    }

    public function test_admin_cannot_reuse_level_name_in_same_workline_but_can_reuse_it_in_another_workline(): void
    {
        $admin = $this->createAdmin();
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายวิชาการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('worklines')->insert([
            'name' => 'สายสนับสนุน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายวิชาการ',
                'name' => 'ระดับ 1',
                'expected_level' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายสนับสนุน',
                'name' => 'ระดับ 1',
                'expected_level' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('admin.structure.levels.store'), [
                'workline_name' => 'สายวิชาการ',
                'name' => 'ระดับ 1',
                'expected_level' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['name']);

        $this->assertSame(2, DB::table('levels')->where('name', 'ระดับ 1')->count());
    }

    public function test_learning_methods_are_fixed_and_cannot_be_mutated(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->post(route('admin.structure.learning-methods.store'), [
                'key' => 'test-learning',
                'label' => 'ประเภททดสอบ',
                'description' => 'รายละเอียดทดสอบ',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.structure.learning-methods.update'), [
                'old_key' => 'test-learning',
                'key' => 'test-learning',
                'label' => 'ประเภทแก้ไข',
                'description' => 'รายละเอียดแก้ไข',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('admin.structure.learning-methods.destroy'), ['key' => 'test-learning'])
            ->assertForbidden();

        $this->assertDatabaseMissing('learning_method_types', ['key' => 'test-learning']);
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'admin')->value('id'),
        ]);
    }
}
