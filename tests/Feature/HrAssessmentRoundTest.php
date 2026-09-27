<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HrAssessmentRoundTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_create_an_active_assessment_round_and_previous_round_is_closed(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $oldRound = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบเดิม',
            'year' => 2568,
            'self_assess_start' => '2025-10-01',
            'self_assess_end' => '2025-10-15',
            'supervisor_assess_end' => '2025-10-31',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->actingAs($hr)->post(route('hr.assessment-rounds.store'), [
            'name' => 'รอบประเมินประจำปี 2569',
            'year' => 2569,
            'self_assess_start' => '2026-10-01',
            'self_assess_end' => '2026-10-15',
            'supervisor_assess_end' => '2026-10-31',
            'is_active' => true,
            'copy_from_round_id' => $oldRound,
        ])->assertRedirect();

        $this->assertDatabaseHas('assessment_rounds', [
            'name' => 'รอบประเมินประจำปี 2569',
            'year' => 2569,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('assessment_rounds', ['id' => $oldRound, 'is_active' => false]);
        $this->assertSame(1, DB::table('assessment_rounds')->where('is_active', true)->count());
    }

    public function test_round_year_is_derived_from_the_start_date_without_manual_input(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);

        $this->actingAs($hr)->post(route('hr.assessment-rounds.store'), [
            'name' => 'รอบทดสอบปีอัตโนมัติ',
            'self_assess_start' => '2027-10-01',
            'self_assess_end' => '2027-10-15',
            'supervisor_assess_end' => '2027-10-31',
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'name' => 'รอบทดสอบปีอัตโนมัติ',
            'year' => 2570,
        ]);
    }

    public function test_editing_a_round_with_assessments_preserves_its_existing_year(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $roundId = $this->round('รอบที่มีผลประเมิน', 2569, false);
        [, $competencyId] = $this->positionAndCompetency();
        DB::table('assessments')->insert([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'status' => 'draft',
            'score' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hr)->put(route('hr.assessment-rounds.update', $roundId), [
            'name' => 'รอบที่มีผลประเมิน',
            'self_assess_start' => '2027-01-01',
            'self_assess_end' => '2027-01-15',
            'supervisor_assess_end' => '2027-01-31',
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'year' => 2569,
            'self_assess_start' => '2027-01-01',
        ]);
    }

    public function test_editing_an_unused_round_updates_its_internal_year_from_the_start_date(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $roundId = $this->round('รอบที่ยังไม่มีผลประเมิน', 2569, false);

        $this->actingAs($hr)->put(route('hr.assessment-rounds.update', $roundId), [
            'name' => 'รอบที่ยังไม่มีผลประเมิน',
            'self_assess_start' => '2028-01-01',
            'self_assess_end' => '2028-01-15',
            'supervisor_assess_end' => '2028-01-31',
            'is_active' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'year' => 2571,
        ]);
    }

    public function test_hr_can_activate_an_existing_round_and_dashboard_receives_rounds(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $first = $this->round('รอบ 2568', 2568, true);
        $second = $this->round('รอบ 2569', 2569, false);
        $this->actingAs($hr)
            ->patch(route('hr.assessment-rounds.activate', $second))
            ->assertRedirect();

        $this->assertDatabaseHas('assessment_rounds', ['id' => $first, 'is_active' => false]);
        $this->assertDatabaseHas('assessment_rounds', ['id' => $second, 'is_active' => true]);

        $this->actingAs($hr)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('assessmentRounds.0.id', $second)
                ->where('assessmentRounds.0.isActive', true)
                ->where('assessmentRounds.1.id', $first));
    }

    public function test_non_hr_cannot_manage_assessment_rounds(): void
    {
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);

        $this->actingAs($employee)->post(route('hr.assessment-rounds.store'), [
            'name' => 'รอบที่ไม่ควรสร้างได้',
            'year' => 2569,
            'is_active' => true,
        ])->assertForbidden();

        $this->assertDatabaseMissing('assessment_rounds', ['name' => 'รอบที่ไม่ควรสร้างได้']);
    }

    public function test_dashboard_round_payload_counts_submitted_people_without_counting_duplicate_competencies(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        User::factory()->create(['role_id' => $this->roleId('employee'), 'is_active' => false]);
        User::factory()->create(['role_id' => $this->roleId('admin')]);
        User::factory()->create(['role_id' => $this->roleId('dean')]);
        $roundId = $this->round('รอบนับผู้ส่ง', 2569, true);
        [, $firstCompetencyId] = $this->positionAndCompetency();
        $typeId = (int) DB::table('competencies')->where('id', $firstCompetencyId)->value('competency_type_id');
        $secondCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-ROUND-02',
            'name' => 'สมรรถนะทดสอบรอบ 2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$firstCompetencyId, $secondCompetencyId] as $competencyId) {
            DB::table('assessments')->insert([
                'assessment_round_id' => $roundId,
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
                'status' => 'self_submitted',
                'score' => 0,
                'note' => '',
                'self_submitted_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($hr)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('assessmentRounds.0.id', $roundId)
                ->where('assessmentRounds.0.submittedUserCount', 1)
                ->where('assessmentRounds.0.eligibleUserCount', 2));
    }

    public function test_hr_can_create_round_with_position_configuration_copied_from_existing_data(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $sourceRoundId = $this->round('ข้อมูลการประเมินเดิม', 2568, false);
        [$positionId, $competencyId] = $this->positionAndCompetency();
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

        $this->actingAs($hr)->post(route('hr.assessment-rounds.store'), [
            'name' => 'รอบประเมินประจำปี 2569',
            'year' => 2569,
            'is_active' => false,
            'copy_from_round_id' => $sourceRoundId,
        ])->assertRedirect();

        $targetRoundId = (int) DB::table('assessment_rounds')
            ->where('name', 'รอบประเมินประจำปี 2569')
            ->value('id');
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
        $this->assertSame(0, DB::table('assessments')->where('assessment_round_id', $targetRoundId)->count());
    }

    public function test_round_dates_must_be_in_order(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);

        $this->actingAs($hr)->post(route('hr.assessment-rounds.store'), [
            'name' => 'รอบวันที่ผิด',
            'year' => 2569,
            'self_assess_start' => '2026-10-15',
            'self_assess_end' => '2026-10-01',
            'supervisor_assess_end' => '2026-09-30',
            'is_active' => true,
        ])->assertSessionHasErrors(['self_assess_end', 'supervisor_assess_end']);
    }

    public function test_round_can_be_activated_when_individual_user_configuration_is_incomplete(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $roundId = $this->round('รอบพร้อมตามวันที่', 2569, false);

        $this->actingAs($hr)
            ->patch(route('hr.assessment-rounds.activate', $roundId))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'is_active' => true,
        ]);
    }

    public function test_round_without_complete_dates_can_not_be_activated(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบวันที่ไม่ครบ',
            'year' => 2569,
            'self_assess_start' => null,
            'self_assess_end' => null,
            'supervisor_assess_end' => null,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hr)
            ->patch(route('hr.assessment-rounds.activate', $roundId))
            ->assertSessionHasErrors('round');

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'is_active' => false,
        ]);
    }

    public function test_round_with_invalid_date_order_can_not_be_activated(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบวันที่เรียงผิด',
            'year' => 2569,
            'self_assess_start' => '2026-10-15',
            'self_assess_end' => '2026-10-01',
            'supervisor_assess_end' => '2026-09-30',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($hr)
            ->patch(route('hr.assessment-rounds.activate', $roundId))
            ->assertSessionHasErrors('round');

        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'is_active' => false,
        ]);
    }

    private function round(string $name, int $year, bool $active): int
    {
        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => $name,
            'year' => $year,
            'self_assess_start' => now()->subMonth()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => $active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->orWhere('role_key', $key)->value('id');
    }

    private function positionAndCompetency(): array
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายทดสอบรอบ', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานทดสอบรอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'ตำแหน่งทดสอบรอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC-ROUND',
            'full_name' => 'Round competency',
            'description' => 'Round competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-ROUND-01',
            'name' => 'สมรรถนะทดสอบรอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $levelId = DB::table('levels')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'ระดับทดสอบรอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$positionId, $competencyId, $levelId];
    }
}
