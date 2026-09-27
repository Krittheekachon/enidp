<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentRoundDeadlineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_employee_cannot_save_or_submit_after_self_assessment_deadline(): void
    {
        CarbonImmutable::setTestNow('2026-09-23 12:00:00');
        $this->createActiveRound('2026-09-01', '2026-09-22', '2026-09-30');

        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->assignReviewer($employee, $reviewer);
        $competencyId = $this->competencyId();
        $payload = [
            'competency_id' => $competencyId,
            'checked_indicators' => [],
            'score' => 2,
            'note' => 'ทดสอบ deadline',
        ];

        $this->actingAs($employee)
            ->postJson(route('assessments.draft'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assessment');

        $this->actingAs($employee)
            ->post(route('assessments.save'), $payload)
            ->assertSessionHasErrors('assessment');

        $this->assertDatabaseMissing('assessments', [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
        ]);
        $this->assertDatabaseHas('assessment_rounds', ['is_active' => true]);
    }

    public function test_reviewer_cannot_review_after_supervisor_assessment_deadline(): void
    {
        CarbonImmutable::setTestNow('2026-09-23 12:00:00');
        $roundId = $this->createActiveRound('2026-09-01', '2026-09-15', '2026-09-22');

        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->assignReviewer($employee, $reviewer);
        $competencyId = $this->competencyId();
        $assessment = Assessment::create([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'score' => 2,
            'status' => 'self_submitted',
        ]);
        DB::table('competency_gaps')->insert([
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'actual_level' => 2,
            'gap' => -1,
            'requires_idp' => true,
            'status' => 'self_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
            ])
            ->assertSessionHasErrors('assessment');

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'self_submitted',
        ]);
        $this->assertDatabaseHas('assessment_rounds', [
            'id' => $roundId,
            'is_active' => true,
        ]);
    }

    public function test_switching_round_starts_new_idp_context_without_reusing_old_gap_or_plan(): void
    {
        $oldRoundId = $this->createActiveRound('2026-01-01', '2026-01-31', '2026-02-15');
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $competencyId = $this->competencyId();
        $assessment = Assessment::create([
            'assessment_round_id' => $oldRoundId,
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'score' => 2,
            'status' => 'approved',
        ]);
        $gapId = DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'expected_level' => 3,
            'actual_level' => 2,
            'gap' => -1,
            'requires_idp' => true,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $oldIdpId = DB::table('idps')->insertGetId([
            'assessment_round_id' => $oldRoundId,
            'user_id' => $employee->id,
            'year' => 2569,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('idp_items')->insert([
            'idp_id' => $oldIdpId,
            'competency_gap_id' => $gapId,
            'goal' => 'แผนของรอบเก่า',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('assessment_rounds')->where('id', $oldRoundId)->update(['is_active' => false]);
        $newRoundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบใหม่',
            'year' => 2570,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'พยายามใช้ Gap รอบเก่า',
                    'successCriteria' => '',
                    'activities' => [],
                ]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseMissing('idps', [
            'assessment_round_id' => $newRoundId,
            'user_id' => $employee->id,
        ]);
        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('currentUserIdp', null));
    }

    private function createActiveRound(string $start, string $selfEnd, string $reviewEnd): int
    {
        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบ deadline',
            'year' => 2569,
            'self_assess_start' => $start,
            'self_assess_end' => $selfEnd,
            'supervisor_assess_end' => $reviewEnd,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assignReviewer(User $employee, User $reviewer): void
    {
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $reviewer->id,
            'step_order' => 1,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function competencyId(): int
    {
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC-DEADLINE-TYPE',
            'full_name' => 'Core Competency',
            'description' => 'Core Competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-DEADLINE',
            'name' => 'สมรรถนะทดสอบ deadline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}
