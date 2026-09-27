<?php

namespace Tests\Feature;

use App\Mail\FcTopicSelectionStatusUpdateMail;
use App\Mail\FcTopicSelectionSubmittedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FcTopicSelectionFlowTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_NOTIFICATION_RECIPIENT = 'developer@example.test';

    public function test_employee_assigned_as_first_reviewer_sees_fc_topic_approval_module(): void
    {
        DB::table('assessment_rounds')->insert([
            'name' => 'รอบทดสอบผู้อนุมัติ FC',
            'year' => 2569,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'is_active' => true,
        ]);

        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $reviewer->id,
            'step_order' => 3,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->where('roleKey', 'employee')
                ->where('fcTopicApprovalModule.enabled', true)
                ->has('fcTopicApprovalModule.items', 0));
    }

    public function test_current_first_reviewer_can_take_over_a_submitted_fc_selection_after_chain_changes(): void
    {
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบเปลี่ยนผู้ตรวจ FC',
            'year' => 2569,
            'self_assess_start' => now()->subMonth()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        [$positionId] = $this->positionWithCompetencies($roundId);
        $previousReviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $currentReviewer = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'position_id' => $positionId,
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $currentReviewer->id,
            'step_order' => 2,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $selectionId = DB::table('fc_topic_selections')->insertGetId([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'position_id' => $positionId,
            'status' => 'submitted',
            'submitted_to' => $previousReviewer->id,
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($currentReviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->where('fcTopicApprovalModule.items.0.id', $selectionId));

        $this->actingAs($previousReviewer)
            ->post(route('fc-topic-selections.approve'), ['selection_id' => $selectionId])
            ->assertSessionHasErrors('selection');

        $this->actingAs($currentReviewer)
            ->post(route('fc-topic-selections.approve'), ['selection_id' => $selectionId])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fc_topic_selections', [
            'id' => $selectionId,
            'status' => 'approved',
            'reviewed_by' => $currentReviewer->id,
        ]);
    }

    public function test_employee_must_get_first_supervisor_approval_for_fc_topics_before_assessment(): void
    {
        Mail::fake();

        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบ FC',
            'year' => 2569,
            'self_assess_start' => now()->subMonth()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$positionId, $ccId, $selectedFcId, $otherFcId] = $this->positionWithCompetencies($roundId);

        DB::table('position_fc_selection_rules')->insert([
            'assessment_round_id' => $roundId,
            'position_id' => $positionId,
            'required_fc_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supervisor = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'position_id' => $positionId,
            'is_active' => true,
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $supervisor->id,
            'step_order' => 1,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $ccId,
                'checked_indicators' => [],
                'score' => 0,
                'note' => '',
            ])
            ->assertSessionHasErrors('assessment');

        $this->actingAs($employee)
            ->post(route('employee.fc-topic-selection.submit'), [
                'competency_ids' => [$selectedFcId],
            ])
            ->assertSessionHasNoErrors();

        $selectionId = (int) DB::table('fc_topic_selections')
            ->where('user_id', $employee->id)
            ->value('id');

        $this->assertDatabaseHas('fc_topic_selections', [
            'id' => $selectionId,
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'position_id' => $positionId,
            'status' => 'submitted',
            'submitted_to' => $supervisor->id,
        ]);
        $this->assertDatabaseHas('fc_topic_selection_items', [
            'fc_topic_selection_id' => $selectionId,
            'competency_id' => $selectedFcId,
        ]);
        Mail::assertSent(FcTopicSelectionSubmittedMail::class, function (FcTopicSelectionSubmittedMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
                && in_array('สมรรถนะ FC1-FLOW-01', $mail->topicNames, true);
        });

        $replacementReviewer = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        DB::table('user_reviewer_steps')
            ->where('user_id', $employee->id)
            ->where('chain_type', 'assessment')
            ->update(['reviewer_id' => $replacementReviewer->id]);

        $this->actingAs($supervisor)
            ->post(route('fc-topic-selections.approve'), ['selection_id' => $selectionId])
            ->assertSessionHasErrors('selection');
        $this->assertDatabaseHas('fc_topic_selections', [
            'id' => $selectionId,
            'status' => 'submitted',
        ]);

        DB::table('user_reviewer_steps')
            ->where('user_id', $employee->id)
            ->where('chain_type', 'assessment')
            ->update(['reviewer_id' => $supervisor->id]);

        $this->actingAs($supervisor)
            ->post(route('fc-topic-selections.reject'), [
                'selection_id' => $selectionId,
                'comment' => '',
            ])
            ->assertSessionHasErrors('comment');

        $this->actingAs($supervisor)
            ->post(route('fc-topic-selections.reject'), [
                'selection_id' => $selectionId,
                'comment' => 'เลือกใหม่ให้ตรงงานที่รับผิดชอบ',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fc_topic_selections', [
            'id' => $selectionId,
            'status' => 'revision_required',
            'review_comment' => 'เลือกใหม่ให้ตรงงานที่รับผิดชอบ',
        ]);
        Mail::assertSent(FcTopicSelectionStatusUpdateMail::class, function (FcTopicSelectionStatusUpdateMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
                && $mail->status === 'revision_required'
                && $mail->comment === 'เลือกใหม่ให้ตรงงานที่รับผิดชอบ';
        });

        $this->actingAs($employee)
            ->post(route('employee.fc-topic-selection.submit'), [
                'competency_ids' => [$selectedFcId],
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($supervisor)
            ->post(route('fc-topic-selections.approve'), [
                'selection_id' => $selectionId,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fc_topic_selections', [
            'id' => $selectionId,
            'status' => 'approved',
            'reviewed_by' => $supervisor->id,
        ]);
        Mail::assertSent(FcTopicSelectionStatusUpdateMail::class, function (FcTopicSelectionStatusUpdateMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
                && $mail->status === 'approved';
        });

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $ccId,
                'checked_indicators' => [],
                'score' => 0,
                'note' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'user_id' => $employee->id,
            'competency_id' => $ccId,
            'status' => 'self_submitted',
        ]);

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $otherFcId,
                'checked_indicators' => [],
                'score' => 0,
                'note' => '',
            ])
            ->assertSessionHasErrors('assessment');
    }

    public function test_employee_can_submit_fc_topics_before_self_assessment_window_opens(): void
    {
        Mail::fake();

        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบส่ง FC ล่วงหน้า',
            'year' => 2569,
            'self_assess_start' => now()->addWeek()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$positionId, , $selectedFcId] = $this->positionWithCompetencies($roundId);

        DB::table('position_fc_selection_rules')->insert([
            'assessment_round_id' => $roundId,
            'position_id' => $positionId,
            'required_fc_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supervisor = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'position_id' => $positionId,
            'is_active' => true,
        ]);

        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $supervisor->id,
            'step_order' => 1,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.fc-topic-selection.submit'), [
                'competency_ids' => [$selectedFcId],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fc_topic_selections', [
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'status' => 'submitted',
            'submitted_to' => $supervisor->id,
        ]);
        Mail::assertSent(FcTopicSelectionSubmittedMail::class, fn (FcTopicSelectionSubmittedMail $mail): bool =>
            $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
        );
    }

    private function positionWithCompetencies(int $roundId): array
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายทดสอบ FC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานทดสอบ FC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'ตำแหน่งทดสอบ FC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ccTypeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'Core competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fc1TypeId = DB::table('competency_types')->insertGetId([
            'code' => 'FC1',
            'full_name' => 'Functional Competency 1',
            'description' => 'Functional competency level 1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fc2TypeId = DB::table('competency_types')->insertGetId([
            'code' => 'FC2',
            'full_name' => 'Functional Competency 2',
            'description' => 'Functional competency level 2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ccId = $this->competency($ccTypeId, 'CC-FLOW-01');
        $selectedFcId = $this->competency($fc1TypeId, 'FC1-FLOW-01');
        $otherFcId = $this->competency($fc2TypeId, 'FC2-FLOW-02');

        foreach ([$ccId, $selectedFcId, $otherFcId] as $competencyId) {
            DB::table('position_competencies')->insert([
                'assessment_round_id' => $roundId,
                'position_id' => $positionId,
                'competency_id' => $competencyId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$positionId, $ccId, $selectedFcId, $otherFcId];
    }

    private function competency(int $typeId, string $code): int
    {
        return DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => $code,
            'name' => 'สมรรถนะ '.$code,
            'detail' => 'รายละเอียด '.$code,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}
