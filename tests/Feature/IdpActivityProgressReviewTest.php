<?php

namespace Tests\Feature;

use App\Mail\IdpProgressApprovedMail;
use App\Mail\IdpProgressReturnedMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IdpActivityProgressReviewTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_NOTIFICATION_RECIPIENT = 'krittheekachon.s@kkumail.com';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_current_reviewer_can_approve_the_completed_competency(): void
    {
        Mail::fake();
        [$reviewer, $completionPublicId, $activityId] = $this->pendingCompletion();

        $this->actingAs($reviewer)->post(route('idp-completions.approve'), [
            'completionPublicId' => $completionPublicId,
            'operationStatus' => 'as_planned',
            'achievementStatus' => 'met',
            'comment' => 'ผลงานเป็นไปตามเป้าหมาย',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_item_completion_submissions', [
            'public_id' => $completionPublicId, 'status' => 'approved', 'current_review_step' => null,
        ]);
        $this->assertDatabaseHas('idp_item_completion_reviews', [
            'reviewer_id' => $reviewer->id, 'decision' => 'approved', 'review_step' => 1,
        ]);
        $this->assertDatabaseHas('idp_activities', ['id' => $activityId, 'status' => 'completed']);
        $this->assertDatabaseHas('idp_activities', ['id' => $activityId, 'result' => 'completed']);

        $employee = User::findOrFail((int) DB::table('idp_activity_updates')->where('activity_id', $activityId)->value('updated_by'));
        Mail::assertSent(IdpProgressApprovedMail::class, function (IdpProgressApprovedMail $mail) use ($employee): bool {
            $mail->assertSeeInHtml('บรรลุตามเป้าหมาย');

            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
                && $mail->achievementStatus === 'met'
                && $mail->comment === 'ผลงานเป็นไปตามเป้าหมาย';
        });
        $this->actingAs($employee)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.reviewData.operationStatus', 'as_planned')
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.reviewData.achievementStatus', 'met'));
    }

    public function test_only_the_first_idp_reviewer_approves_completion_and_later_reviewers_are_read_only(): void
    {
        [$firstReviewer, $completionPublicId, $activityId] = $this->pendingCompletion();
        $employeeId = (int) DB::table('idp_activity_updates')->where('activity_id', $activityId)->value('updated_by');
        $secondReviewer = User::factory()->create(['role_id' => $this->roleId('dept_head')]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employeeId,
            'chain_type' => 'idp',
            'step_order' => 2,
            'reviewer_id' => $secondReviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($secondReviewer)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('idpProgressReviewItems', 1)
                ->where('idpProgressReviewItems.0.canReview', false));

        $this->actingAs($secondReviewer)->post(route('idp-completions.approve'), [
            'completionPublicId' => $completionPublicId,
            'operationStatus' => 'as_planned',
            'achievementStatus' => 'met',
        ])->assertSessionHasErrors('completionPublicId');

        $this->actingAs($firstReviewer)->post(route('idp-completions.approve'), [
            'completionPublicId' => $completionPublicId,
            'operationStatus' => 'as_planned',
            'achievementStatus' => 'met',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_item_completion_submissions', [
            'public_id' => $completionPublicId,
            'status' => 'approved',
            'current_review_step' => null,
        ]);
        $this->assertDatabaseMissing('idp_item_completion_reviews', [
            'reviewer_id' => $secondReviewer->id,
        ]);
    }

    public function test_final_competency_result_comes_from_the_reviewer_not_individual_topics(): void
    {
        [$reviewer, $completionPublicId, $activityId] = $this->pendingCompletion();

        $this->actingAs($reviewer)->post(route('idp-completions.approve'), [
            'completionPublicId' => $completionPublicId,
            'operationStatus' => 'not_as_planned',
            'operationReason' => 'ดำเนินการได้ไม่ครบตามแผน',
            'achievementStatus' => 'not_met',
            'achievementNote' => 'ควรพัฒนาต่อในรอบถัดไป',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_activities', [
            'id' => $activityId,
            'status' => 'completed',
            'result' => 'incomplete',
        ]);
        $this->assertDatabaseHas('idp_item_completion_submissions', [
            'public_id' => $completionPublicId,
            'status' => 'approved',
            'result' => 'failed',
        ]);
        $oldItemId = (int) DB::table('idp_activities')->where('id', $activityId)->value('idp_item_id');
        $oldItem = DB::table('idp_items')->where('id', $oldItemId)->first();
        $this->assertDatabaseHas('idp_items', [
            'idp_id' => $oldItem->idp_id,
            'competency_gap_id' => $oldItem->competency_gap_id,
            'status' => 'draft',
            'submission_version' => 0,
        ]);
        $this->assertSame(2, DB::table('idp_items')
            ->where('idp_id', $oldItem->idp_id)
            ->where('competency_gap_id', $oldItem->competency_gap_id)
            ->count());

        $employee = User::findOrFail((int) DB::table('idps')->where('id', $oldItem->idp_id)->value('user_id'));
        $this->actingAs($employee)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserIdp.items.0.status', 'draft')
                ->where('currentUserIdp.items.0.goal', '')
                ->where('currentUserIdp.items.0.successCriteria', '')
                ->has('currentUserIdp.items.0.activities', 0)
                ->where('currentUserApprovedIdpActivities.0.completion.result', 'failed'));
    }

    public function test_current_reviewer_can_return_completion_without_changing_submitted_updates(): void
    {
        Mail::fake();
        [$reviewer, $completionPublicId, $activityId, $updateId] = $this->pendingCompletion();

        $this->actingAs($reviewer)->post(route('idp-completions.reject'), [
            'completionPublicId' => $completionPublicId,
            'comment' => 'กรุณาเพิ่มหลักฐานผลลัพธ์',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_item_completion_submissions', [
            'public_id' => $completionPublicId, 'status' => 'revision_required',
        ]);
        $this->assertDatabaseHas('idp_activity_updates', [
            'id' => $updateId, 'status' => 'submitted',
        ]);

        $employee = User::findOrFail((int) DB::table('idp_activity_updates')->where('activity_id', $activityId)->value('updated_by'));
        Mail::assertSent(IdpProgressReturnedMail::class, function (IdpProgressReturnedMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
                && $mail->comment === 'กรุณาเพิ่มหลักฐานผลลัพธ์';
        });
        $this->actingAs($employee)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserApprovedIdpActivities.0.completion.status', 'revision_required')
                ->where('currentUserApprovedIdpActivities.0.completion.review.reviewerName', $reviewer->name)
                ->where('currentUserApprovedIdpActivities.0.completion.review.decision', 'rejected')
                ->where('currentUserApprovedIdpActivities.0.completion.review.comment', 'กรุณาเพิ่มหลักฐานผลลัพธ์')
                ->has('currentUserApprovedIdpActivities.0.completion.reviewHistory', 1)
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.reviewerName', $reviewer->name)
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.decision', 'rejected')
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.comment', 'กรุณาเพิ่มหลักฐานผลลัพธ์'));
    }

    public function test_returned_completion_preserves_optional_review_results_when_the_reviewer_fills_them(): void
    {
        Carbon::setTestNow('2026-09-23 04:07:04');
        [$reviewer, $completionPublicId, $activityId] = $this->pendingCompletion();

        $this->actingAs($reviewer)->post(route('idp-completions.reject'), [
            'completionPublicId' => $completionPublicId,
            'comment' => 'กรุณาแก้ไขหลักฐาน',
            'operationStatus' => 'not_as_planned',
            'achievementStatus' => 'not_met',
        ])->assertSessionHasNoErrors();

        $review = DB::table('idp_item_completion_reviews')->latest('id')->first();
        $this->assertSame('rejected', $review->decision);
        $this->assertSame([
            'operationStatus' => 'not_as_planned',
            'operationReason' => null,
            'achievementStatus' => 'not_met',
            'achievementNote' => null,
        ], json_decode($review->review_data, true));

        $employee = User::findOrFail((int) DB::table('idp_activity_updates')->where('activity_id', $activityId)->value('updated_by'));
        $this->actingAs($employee)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.reviewData.operationStatus', 'not_as_planned')
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.reviewData.achievementStatus', 'not_met')
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.comment', 'กรุณาแก้ไขหลักฐาน')
                ->where('currentUserApprovedIdpActivities.0.completion.reviewHistory.0.decidedAt', '2026-09-23T04:07:04.000000Z'));
    }

    public function test_unassigned_reviewer_cannot_review_completion(): void
    {
        [, $completionPublicId] = $this->pendingCompletion();
        $other = User::factory()->create(['role_id' => $this->roleId('supervisor')]);

        $this->actingAs($other)->post(route('idp-completions.approve'), [
            'completionPublicId' => $completionPublicId,
            'operationStatus' => 'as_planned',
            'achievementStatus' => 'met',
        ])->assertSessionHasErrors('completionPublicId');
    }

    public function test_reviewer_dashboard_shows_submitted_timeline_but_never_the_employee_draft(): void
    {
        [$reviewer, , $activityId] = $this->pendingCompletion();
        $employeeId = (int) DB::table('idp_activity_updates')->where('activity_id', $activityId)->value('updated_by');
        $completionUpdatedAt = DB::table('idp_item_completion_submissions')->value('updated_at');
        DB::table('idp_activity_updates')->insert([
            'public_id' => (string) Str::uuid(), 'activity_id' => $activityId, 'topic_index' => 0,
            'progress_note' => 'ข้อมูลลับในฉบับร่าง', 'submission_version' => 1,
            'updated_by' => $employeeId, 'status' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($reviewer)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('idpProgressReviewItems', 1)
                ->where('idpProgressReviewItems.0.planStatus', 'approved')
                ->where('idpProgressReviewItems.0.currentReviewerName', $reviewer->name)
                ->where('idpProgressReviewItems.0.completionUpdatedAt', Carbon::parse($completionUpdatedAt, 'UTC')->utc()->toISOString())
                ->where('idpProgressReviewItems.0.activities.0.updates.0.progressNote', 'พัฒนาระบบแล้ว')
                ->missing('idpProgressReviewItems.0.activities.0.updates.1'));
    }

    public function test_reviewer_dashboard_shows_every_failed_competency_even_before_an_idp_plan_exists(): void
    {
        [$reviewer, , $activityId] = $this->pendingCompletion();
        $employeeId = (int) DB::table('idp_activity_updates')->where('activity_id', $activityId)->value('updated_by');
        $this->approvedGapWithoutIdp($employeeId);

        $this->actingAs($reviewer)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('idpProgressReviewItems', 2)
                ->where('teamIdpAnalytics.idpSummary.requiredEmployees', 1)
                ->where('teamIdpAnalytics.idpSummary.totalItems', 2)
                ->has('teamIdpAnalytics.idpDetails', 2)
                ->where('teamIdpAnalytics.heatmap.0.faculty.people.0.userId', $employeeId));
    }

    public function test_team_idp_tracking_includes_assessment_member_without_an_idp_reviewer_chain(): void
    {
        [$reviewer] = $this->pendingCompletion();
        $assessmentOnlyMember = User::factory()->create(['role_id' => $this->roleId('employee')]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $assessmentOnlyMember->id,
            'chain_type' => 'assessment',
            'step_order' => 1,
            'reviewer_id' => $reviewer->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->approvedGapWithoutIdp($assessmentOnlyMember->id);

        $this->actingAs($reviewer)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('teamIdpAnalytics.idpSummary.requiredEmployees', 2)
                ->where('teamIdpAnalytics.idpSummary.startedEmployees', 1)
                ->where('teamIdpAnalytics.idpSummary.notStartedEmployees', 1)
                ->has('teamIdpAnalytics.idpDetails', 2)
                ->where('teamIdpAnalytics.idpDetails', fn ($items): bool => $items->contains(
                    fn (array $item): bool => (int) $item['userId'] === $assessmentOnlyMember->id
                        && $item['planStatus'] === 'not_started',
                )));
    }

    public function test_reviewer_dashboard_marks_unsubmitted_idp_as_overdue_after_its_due_date(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');
        [$reviewer, , $activityId] = $this->pendingCompletion();
        DB::table('idp_activities')->where('id', $activityId)->update(['end_date' => '2026-10-01']);
        DB::table('idp_item_completion_submissions')->delete();

        $this->actingAs($reviewer)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('idpProgressReviewItems.0.dueDate', '2026-10-01')
                ->where('idpProgressReviewItems.0.isOverdue', true)
                ->where('idpProgressReviewItems.0.daysOverdue', 4));
    }

    public function test_idp_waiting_for_review_is_not_active_overdue_work(): void
    {
        Carbon::setTestNow('2026-10-05 09:00:00');
        [$reviewer, , $activityId] = $this->pendingCompletion();
        DB::table('idp_activities')->where('id', $activityId)->update(['end_date' => '2026-10-01']);

        $this->actingAs($reviewer)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('idpProgressReviewItems.0.dueDate', '2026-10-01')
                ->where('idpProgressReviewItems.0.isOverdue', false)
                ->where('idpProgressReviewItems.0.daysOverdue', 0));
    }

    private function approvedGapWithoutIdp(int $employeeId): int
    {
        $typeId = (int) DB::table('competency_types')->value('id');
        $roundId = (int) DB::table('assessment_rounds')->value('id');
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-NO-UPDATE',
            'name' => 'สมรรถนะที่ยังไม่มีอัปเดต',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $assessmentId = DB::table('assessments')->insertGetId([
            'assessment_round_id' => $roundId,
            'user_id' => $employeeId,
            'competency_id' => $competencyId,
            'score' => 2,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessmentId,
            'competency_id' => $competencyId,
            'expected_level' => 3,
            'actual_level' => 2,
            'gap' => -1,
            'requires_idp' => true,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function pendingCompletion(): array
    {
        $reviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id, 'chain_type' => 'idp', 'step_order' => 1,
            'reviewer_id' => $reviewer->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $employee->id, 'year' => 2569, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC-TYPE', 'full_name' => 'Core Competency', 'description' => 'Core Competency',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId, 'code' => 'CC-PROGRESS', 'name' => 'สมรรถนะทดสอบความก้าวหน้า',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบ', 'year' => 2569, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $assessmentId = DB::table('assessments')->insertGetId([
            'assessment_round_id' => $roundId, 'user_id' => $employee->id, 'competency_id' => $competencyId,
            'score' => 2, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $gapId = DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessmentId, 'competency_id' => $competencyId,
            'expected_level' => 3, 'actual_level' => 2, 'gap' => -1, 'requires_idp' => true, 'status' => 'approved',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $itemId = DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId, 'competency_gap_id' => $gapId, 'goal' => 'พัฒนางาน', 'status' => 'approved', 'submission_version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = DB::table('idp_activities')->insertGetId([
            'idp_item_id' => $itemId, 'activity_name' => 'โครงการพัฒนาระบบ',
            'form_code' => 'form_3_project_assignment',
            'form_details' => json_encode(['planRows' => [['assignmentTopic' => 'พัฒนาระบบ']]], JSON_UNESCAPED_UNICODE),
            'status' => 'in_progress', 'result' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $updateId = DB::table('idp_activity_updates')->insertGetId([
            'public_id' => (string) Str::uuid(), 'activity_id' => $activityId, 'topic_index' => 0,
            'period_start' => '2026-09-01', 'period_end' => '2026-09-03',
            'progress_note' => 'พัฒนาระบบแล้ว', 'submission_version' => 1,
            'updated_by' => $employee->id, 'status' => 'submitted', 'submitted_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $completionPublicId = (string) Str::uuid();
        DB::table('idp_item_completion_submissions')->insert([
            'public_id' => $completionPublicId, 'idp_item_id' => $itemId,
            'submission_version' => 1, 'status' => 'review_step_1', 'current_review_step' => 1,
            'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$reviewer, $completionPublicId, $activityId, $updateId];
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }
}
