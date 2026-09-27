<?php

namespace Tests\Feature;

use App\Mail\IdpProgressSubmittedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Inertia\Testing\AssertableInertia as Assert;

class IdpActivityUpdateTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_NOTIFICATION_RECIPIENT = 'krittheekachon.s@kkumail.com';

    public function test_employee_cannot_save_an_incomplete_progress_draft(): void
    {
        [$employee, $activityId] = $this->approvedActivity();

        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'action' => 'draft',
            'topicIndex' => 0,
            'progressNote' => 'เริ่มออกแบบแล้ว',
        ])->assertSessionHasErrors('action');

        $this->assertDatabaseCount('idp_activity_updates', 0);
    }

    public function test_submitting_progress_requires_dates_and_work_done(): void
    {
        [$employee, $activityId] = $this->approvedActivity();

        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'action' => 'submit',
            'topicIndex' => 0,
        ])->assertSessionHasErrors(['periodStart', 'periodEnd', 'progressNote']);
    }

    public function test_end_date_cannot_precede_start_date(): void
    {
        [$employee, $activityId] = $this->approvedActivity();

        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'action' => 'submit',
            'topicIndex' => 0,
            'periodStart' => '2026-09-13',
            'periodEnd' => '2026-09-12',
            'progressNote' => 'ดำเนินการแล้ว',
        ])->assertSessionHasErrors('periodEnd');
    }

    public function test_employee_can_submit_multiple_progress_entries_with_private_evidence(): void
    {
        Storage::fake('local');
        [$employee, $activityId] = $this->approvedActivity();

        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'action' => 'submit',
            'topicIndex' => 0,
            'periodStart' => '2026-09-01',
            'periodEnd' => '2026-09-03',
            'progressNote' => 'ออกแบบ UI แล้ว',
            'evidenceLinks' => [['url' => 'https://example.test/design', 'description' => 'ต้นแบบ']],
            'evidenceFiles' => [UploadedFile::fake()->image('screen.png')],
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'action' => 'submit',
            'topicIndex' => 0,
            'periodStart' => '2026-09-04',
            'periodEnd' => '2026-09-08',
            'progressNote' => 'พัฒนา backend แล้ว',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('idp_activity_updates', 2);
        $this->assertDatabaseHas('idp_activity_update_evidences', ['kind' => 'link']);
        $this->assertDatabaseHas('idp_activity_update_evidences', ['kind' => 'image', 'original_name' => 'screen.png']);
    }

    public function test_saving_progress_does_not_notify_until_completion_is_submitted(): void
    {
        Mail::fake();
        [$employee, $activityId, $itemId] = $this->approvedActivity();
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'chain_type' => 'idp',
            'step_order' => 1,
            'reviewer_id' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->submitProgress($employee, $activityId, 0);
        Mail::assertNothingSent();

        $this->submitProgress($employee, $activityId, 1);
        $this->actingAs($employee)->post(route('employee.idp-items.submit-completion'), [
            'idpItemId' => $itemId,
        ])->assertSessionHasNoErrors();

        Mail::assertSent(IdpProgressSubmittedMail::class, function (IdpProgressSubmittedMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee);
        });
    }

    public function test_faculty_analytics_viewer_can_open_submitted_evidence_but_unrelated_employee_cannot(): void
    {
        Storage::fake('local');
        [$employee, $activityId] = $this->approvedActivity();

        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'action' => 'submit',
            'topicIndex' => 0,
            'periodStart' => '2026-09-01',
            'periodEnd' => '2026-09-03',
            'progressNote' => 'แนบหลักฐานการดำเนินงานแล้ว',
            'evidenceFiles' => [UploadedFile::fake()->create('result.pdf', 20, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $evidence = DB::table('idp_activity_update_evidences')->firstOrFail();
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $unrelatedEmployee = User::factory()->create(['role_id' => $this->roleId('employee')]);

        $this->actingAs($hr)
            ->get(route('idp-progress.evidence.show', ['evidence' => $evidence->public_id]))
            ->assertOk();
        $this->actingAs($unrelatedEmployee)
            ->get(route('idp-progress.evidence.show', ['evidence' => $evidence->public_id]))
            ->assertForbidden();
    }

    public function test_submitted_progress_is_immutable(): void
    {
        [$employee, $activityId] = $this->approvedActivity();
        $this->submitProgress($employee, $activityId, 0);
        $publicId = DB::table('idp_activity_updates')->value('public_id');

        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'updatePublicId' => $publicId,
            'action' => 'submit',
            'topicIndex' => 0,
            'periodStart' => '2026-09-01',
            'periodEnd' => '2026-09-03',
            'progressNote' => 'พยายามแก้รายการที่ส่งแล้ว',
        ])->assertSessionHasErrors('updatePublicId');
    }

    public function test_draft_request_cannot_create_private_evidence(): void
    {
        Storage::fake('local');
        [$employee, $activityId] = $this->approvedActivity();
        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'action' => 'draft',
            'topicIndex' => 0,
            'evidenceFiles' => [UploadedFile::fake()->create('draft.pdf', 20, 'application/pdf')],
        ])->assertSessionHasErrors('action');

        $this->assertDatabaseCount('idp_activity_update_evidences', 0);
    }

    public function test_completion_requires_a_submitted_update_for_every_topic(): void
    {
        [$employee, $activityId, $itemId] = $this->approvedActivity();
        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id, 'chain_type' => 'idp', 'step_order' => 1,
            'reviewer_id' => $reviewer->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($employee)->post(route('employee.idp-items.submit-completion'), [
            'idpItemId' => $itemId,
        ])->assertSessionHasErrors('idpItemId');

        $this->submitProgress($employee, $activityId, 0);
        $this->actingAs($employee)->post(route('employee.idp-items.submit-completion'), [
            'idpItemId' => $itemId,
        ])->assertSessionHasErrors('idpItemId');

        $this->submitProgress($employee, $activityId, 1);
        $this->actingAs($employee)->post(route('employee.idp-items.submit-completion'), [
            'idpItemId' => $itemId,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_item_completion_submissions', [
            'idp_item_id' => $itemId, 'status' => 'review_step_1', 'current_review_step' => 1,
        ]);
    }

    public function test_employee_progress_shows_the_current_completion_reviewer_name_and_step(): void
    {
        [$employee, $activityId, $itemId] = $this->approvedActivity();
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
            'name' => 'ผู้ตรวจสอบคนปัจจุบัน',
            'title' => 'นาย',
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id, 'chain_type' => 'idp', 'step_order' => 1,
            'reviewer_id' => $reviewer->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $competencyTypeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC-REVIEWER-TYPE', 'full_name' => 'Core Competency',
            'description' => 'Core Competency', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $competencyTypeId,
            'code' => 'CC-CURRENT-REVIEWER', 'name' => 'สมรรถนะทดสอบผู้ตรวจปัจจุบัน',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบผู้ตรวจ', 'year' => 2569, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $assessmentId = DB::table('assessments')->insertGetId([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id, 'competency_id' => $competencyId,
            'score' => 2, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gapId = DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessmentId, 'competency_id' => $competencyId,
            'expected_level' => 3, 'actual_level' => 2, 'gap' => -1,
            'requires_idp' => true, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('idp_items')->where('id', $itemId)->update(['competency_gap_id' => $gapId]);
        $this->submitProgress($employee, $activityId, 0);
        $this->submitProgress($employee, $activityId, 1);
        $this->actingAs($employee)->post(route('employee.idp-items.submit-completion'), [
            'idpItemId' => $itemId,
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee)->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('currentUserApprovedIdpActivities.0.completion.currentReviewStep', 1)
            ->where('currentUserApprovedIdpActivities.0.completion.currentReviewerName', 'นายผู้ตรวจสอบคนปัจจุบัน')
        );
    }

    public function test_employee_cannot_update_before_plan_approval(): void
    {
        [$employee, $activityId] = $this->approvedActivity('review_step_1');
        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId, 'action' => 'submit', 'topicIndex' => 0,
            'periodStart' => '2026-09-01', 'periodEnd' => '2026-09-03',
            'progressNote' => 'ดำเนินการแล้ว',
        ])->assertSessionHasErrors('activityId');
    }

    private function submitProgress(User $employee, int $activityId, int $topicIndex): void
    {
        $this->actingAs($employee)->post(route('employee.idp-activities.update-progress'), [
            'activityId' => $activityId,
            'action' => 'submit',
            'topicIndex' => $topicIndex,
            'periodStart' => '2026-09-01',
            'periodEnd' => '2026-09-03',
            'progressNote' => 'ดำเนินการหัวข้อนี้แล้ว',
        ])->assertSessionHasNoErrors();
    }

    private function approvedActivity(string $itemStatus = 'approved'): array
    {
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $employee->id, 'year' => 2569, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId, 'goal' => 'พัฒนาสมรรถนะ', 'success_criteria' => 'ผ่านเกณฑ์',
            'status' => $itemStatus, 'submission_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = DB::table('idp_activities')->insertGetId([
            'idp_item_id' => $itemId,
            'activity_name' => 'โครงการพัฒนาระบบ',
            'form_code' => 'form_3_project_assignment',
            'form_details' => json_encode(['planRows' => [
                ['assignmentTopic' => 'ออกแบบระบบ'],
                ['assignmentTopic' => 'พัฒนาระบบ'],
            ]], JSON_UNESCAPED_UNICODE),
            'weight_percent' => 100,
            'start_date' => '2026-09-01', 'end_date' => '2026-10-01',
            'status' => 'planned', 'result' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$employee, $activityId, $itemId];
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}
