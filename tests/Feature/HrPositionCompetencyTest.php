<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HrPositionCompetencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_attach_and_detach_competencies_to_a_position(): void
    {
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบการกำหนดสมรรถนะ',
            'year' => 2569,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hrUser = User::factory()->create([
            'role_id' => DB::table('roles')->where('key', 'hr')->value('id'),
        ]);
        [$positionId, $competencyId] = $this->createPositionAndCompetency();
        $employee = User::factory()->create([
            'role_id' => DB::table('roles')->where('key', 'employee')->value('id'),
            'position_id' => $positionId,
            'is_active' => true,
        ]);
        $supervisor = User::factory()->create([
            'role_id' => DB::table('roles')->where('key', 'supervisor')->value('id'),
            'position_id' => $positionId,
            'is_active' => true,
        ]);

        $this->actingAs($hrUser)
            ->post(route('hr.position-competencies.store'), [
                'position_id' => $positionId,
                'assessment_round_id' => $roundId,
                'competency_id' => $competencyId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('position_competencies', [
            'position_id' => $positionId,
            'assessment_round_id' => $roundId,
            'competency_id' => $competencyId,
        ]);
        $this->assertDatabaseHas('assessments', [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
        ]);
        $this->assertDatabaseHas('assessments', [
            'user_id' => $supervisor->id,
            'competency_id' => $competencyId,
        ]);

        $this->actingAs($hrUser)
            ->post(route('hr.position-competencies.store'), [
                'position_id' => $positionId,
                'assessment_round_id' => $roundId,
                'competency_id' => $competencyId,
            ])
            ->assertRedirect();

        $this->assertSame(1, DB::table('position_competencies')->count());

        $this->actingAs($hrUser)
            ->delete(route('hr.position-competencies.destroy'), [
                'position_id' => $positionId,
                'assessment_round_id' => $roundId,
                'competency_id' => $competencyId,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('position_competencies', [
            'position_id' => $positionId,
            'assessment_round_id' => $roundId,
            'competency_id' => $competencyId,
        ]);
    }

    public function test_hr_dashboard_loads_position_competency_mapping(): void
    {
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบหน้า HR',
            'year' => 2569,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $hrUser = User::factory()->create([
            'role_id' => DB::table('roles')->where('key', 'hr')->value('id'),
        ]);
        [$positionId, $competencyId] = $this->createPositionAndCompetency();

        DB::table('position_competencies')->insert([
            'assessment_round_id' => $roundId,
            'position_id' => $positionId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where("positionCompetencies.{$roundId}.{$positionId}.0", $competencyId)
            );
    }

    public function test_hr_can_replace_position_competencies_and_fc_rules_by_importing_another_round(): void
    {
        $sourceRoundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบปี 2568',
            'year' => 2568,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $targetRoundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบปี 2569',
            'year' => 2569,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $hrUser = User::factory()->create([
            'role_id' => DB::table('roles')->where('key', 'hr')->value('id'),
        ]);
        [$positionId, $competencyId] = $this->createPositionAndCompetency();
        $otherCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => DB::table('competencies')->where('id', $competencyId)->value('competency_type_id'),
            'code' => 'CC-OLD-TARGET',
            'name' => 'สมรรถนะเดิมของรอบปลายทาง',
            'detail' => 'ต้องถูกแทนที่เมื่อนำเข้า',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('position_competencies')->insert([
            'assessment_round_id' => $sourceRoundId,
            'position_id' => $positionId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('position_fc_selection_rules')->insert([
            'assessment_round_id' => $sourceRoundId,
            'position_id' => $positionId,
            'required_fc_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('position_competencies')->insert([
            'assessment_round_id' => $targetRoundId,
            'position_id' => $positionId,
            'competency_id' => $otherCompetencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('position_fc_selection_rules')->insert([
            'assessment_round_id' => $targetRoundId,
            'position_id' => $positionId,
            'required_fc_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hrUser)
            ->post(route('hr.position-competencies.copy-round'), [
                'source_round_id' => $sourceRoundId,
                'target_round_id' => $targetRoundId,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('position_competencies', [
            'assessment_round_id' => $targetRoundId,
            'position_id' => $positionId,
            'competency_id' => $competencyId,
        ]);
        $this->assertDatabaseHas('position_fc_selection_rules', [
            'assessment_round_id' => $targetRoundId,
            'position_id' => $positionId,
            'required_fc_count' => 0,
        ]);
        $this->assertDatabaseMissing('position_competencies', [
            'assessment_round_id' => $targetRoundId,
            'position_id' => $positionId,
            'competency_id' => $otherCompetencyId,
        ]);
        $this->assertSame(2, DB::table('position_competencies')->count());
    }

    public function test_employee_receives_only_position_competencies_from_active_round(): void
    {
        $previousRoundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบเดิม',
            'year' => 2568,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activeRoundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบปัจจุบัน',
            'year' => 2569,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        [$positionId, $previousCompetencyId] = $this->createPositionAndCompetency();
        $typeId = (int) DB::table('competencies')->where('id', $previousCompetencyId)->value('competency_type_id');
        $activeCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-T02',
            'name' => 'สมรรถนะรอบใหม่',
            'detail' => 'ใช้เฉพาะรอบปัจจุบัน',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('position_competencies')->insert([
            [
                'assessment_round_id' => $previousRoundId,
                'position_id' => $positionId,
                'competency_id' => $previousCompetencyId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'assessment_round_id' => $activeRoundId,
                'position_id' => $positionId,
                'competency_id' => $activeCompetencyId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $employee = User::factory()->create([
            'role_id' => DB::table('roles')->where('key', 'employee')->value('id'),
            'position_id' => $positionId,
            'is_active' => true,
        ]);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->has('currentUserCompetencies', 1)
                ->where('currentUserCompetencies.0.id', $activeCompetencyId)
            );
    }

    private function createPositionAndCompetency(): array
    {
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

        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'ตำแหน่งทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'Core competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-T01',
            'name' => 'สมรรถนะทดสอบ',
            'detail' => 'รายละเอียดทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$positionId, $competencyId];
    }
}
