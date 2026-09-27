<?php

namespace Tests\Feature;

use App\Mail\AssessmentStatusUpdateMail;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AssessmentReviewerChainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assessmentRoundId();
    }

    public function test_employee_role_reviewer_sees_assessment_approval_module_from_runtime_chain(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
            'department' => 'ทดสอบฝ่าย > ทดสอบงาน > ทดสอบหน่วย',
        ]);
        $this->assignAssessmentReviewers($employee, [1 => $reviewer->id]);
        $competencyId = $this->competencyId('CC-EMPLOYEE-REVIEWER');
        $assessment = $this->assessment($employee, $competencyId, 'self_submitted');
        $assessment->forceFill(['last_draft_saved_at' => now()])->save();

        $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->where('roleKey', 'employee')
                ->where('assessmentApprovalModule.enabled', true)
                ->where('assessmentApprovalModule.pendingCount', 1)
                ->where('assessmentApprovalModule.items.0.employeeId', $employee->id)
                ->where('assessmentApprovalModule.items.0.reviewStep', 1)
                ->where('assessmentApprovalModule.items.0.organizationLabel', 'หน่วย')
                ->where('assessmentApprovalModule.items.0.organization', 'ทดสอบหน่วย')
                ->where('assessmentApprovalModule.items.0.competencies.0.competencyId', $competencyId)
                ->where('reviewerTeamUsers', function ($users) use ($reviewer, $employee): bool {
                    $rows = collect($users);
                    $assigned = $rows->firstWhere('db_id', $employee->id);

                    return $rows->pluck('db_id')->sort()->values()->all() === collect([
                        $reviewer->id,
                        $employee->id,
                    ])->sort()->values()->all()
                        && ! array_key_exists('em', $assigned)
                        && ! array_key_exists('username', $assigned)
                        && ! array_key_exists('ph', $assigned);
                })
                ->missing('users')
            );
    }

    public function test_employee_assigned_only_to_idp_chain_keeps_employee_dashboard_and_sees_idp_review_module(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);

        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $reviewer->id,
            'step_order' => 1,
            'chain_type' => 'idp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->where('roleKey', 'employee')
                ->where('assessmentApprovalModule.enabled', false)
                ->where('idpReviewModule.enabled', true)
                ->where('idpReviewModule.assignmentCount', 1)
                ->where('reviewerTeamUsers', null)
            );
    }

    public function test_reviewer_assignment_keeps_each_non_head_role_on_its_own_dashboard(): void
    {
        $cases = [
            'admin' => 'Admin/Dashboard',
            'hr' => 'HR/Dashboard',
        ];

        foreach ($cases as $roleKey => $component) {
            $reviewer = User::factory()->create([
                'role_id' => $this->roleId($roleKey),
            ]);
            $employee = User::factory()->create([
                'role_id' => $this->roleId('employee'),
            ]);
            $unassignedEmployee = User::factory()->create([
                'role_id' => $this->roleId('employee'),
            ]);
            $this->assignAssessmentReviewers($employee, [1 => $reviewer->id]);
            $competencyId = $this->competencyId('CC-'.strtoupper($roleKey).'-REVIEWER');
            $assessment = $this->assessment($employee, $competencyId, 'self_submitted');
            $assessment->forceFill(['last_draft_saved_at' => now()])->save();

            $this->actingAs($reviewer)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('assessmentApprovalModule.enabled', true)
                    ->where('assessmentApprovalModule.items.0.employeeId', $employee->id)
                    ->where('reviewerTeamUsers', fn ($users): bool => collect($users)
                        ->pluck('db_id')->sort()->values()->all() === collect([
                            $reviewer->id,
                            $employee->id,
                        ])->sort()->values()->all()
                        && ! collect($users)->contains('db_id', $unassignedEmployee->id))
                );
        }
    }

    public function test_every_non_head_role_gets_both_reviewer_modules_when_assigned_to_both_chains(): void
    {
        foreach ([
            'employee' => 'Employee/Dashboard',
            'admin' => 'Admin/Dashboard',
            'hr' => 'HR/Dashboard',
            'dean' => 'Executive/Dashboard',
        ] as $roleKey => $component) {
            $reviewer = User::factory()->create(['role_id' => $this->roleId($roleKey)]);
            $assessmentMember = User::factory()->create(['role_id' => $this->roleId('employee')]);
            $idpOnlyMember = User::factory()->create(['role_id' => $this->roleId('employee')]);
            $this->assignAssessmentReviewers($assessmentMember, [1 => $reviewer->id]);
            $competencyId = $this->competencyId('CC-ALL-ROLES-'.strtoupper($roleKey));
            $assessment = $this->assessment($assessmentMember, $competencyId, 'self_submitted');
            $assessment->forceFill(['last_draft_saved_at' => now()])->save();
            DB::table('user_reviewer_steps')->insert([
                'user_id' => $idpOnlyMember->id,
                'reviewer_id' => $reviewer->id,
                'step_order' => 1,
                'chain_type' => 'idp',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->actingAs($reviewer)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('assessmentApprovalModule.enabled', true)
                    ->where('assessmentApprovalModule.pendingCount', 1)
                    ->where('assessmentApprovalModule.items.0.employeeId', $assessmentMember->id)
                    ->where('idpReviewModule.enabled', true)
                    ->where('teamIdpAnalytics.round.id', $this->assessmentRoundId())
                    ->where('reviewerTeamUsers', fn ($users): bool => collect($users)
                        ->pluck('db_id')->sort()->values()->all() === collect([
                            $reviewer->id,
                            $assessmentMember->id,
                        ])->sort()->values()->all()
                        && ! collect($users)->contains('db_id', $idpOnlyMember->id))
                );

            $this->actingAs($reviewer)
                ->post(route('assessments.approve'), [
                    'user_id' => $assessmentMember->id,
                    'competency_id' => $competencyId,
                ])
                ->assertSessionHasNoErrors();
            $this->assertDatabaseHas('assessments', [
                'id' => $assessment->id,
                'status' => 'approved',
            ]);
        }
    }

    public function test_head_without_runtime_assignment_does_not_receive_review_modules(): void
    {
        $supervisor = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Super/Dashboard')
                ->where('assessmentApprovalModule.enabled', false)
                ->where('fcTopicApprovalModule.enabled', false)
                ->where('idpReviewModule.enabled', false)
            );
    }

    public function test_head_dashboard_receives_only_assigned_people_without_login_contact_fields(): void
    {
        $supervisor = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $assignedEmployee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'username' => 'assigned.employee',
            'phone' => '081-111-1111',
        ]);
        $unassignedEmployee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($assignedEmployee, [1 => $supervisor->id]);

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Super/Dashboard')
                ->where('users', function ($users) use ($supervisor, $assignedEmployee, $unassignedEmployee): bool {
                    $rows = collect($users);
                    $assigned = $rows->firstWhere('db_id', $assignedEmployee->id);

                    return $rows->pluck('db_id')->sort()->values()->all() === collect([
                        $supervisor->id,
                        $assignedEmployee->id,
                    ])->sort()->values()->all()
                        && ! $rows->contains('db_id', $unassignedEmployee->id)
                        && ! array_key_exists('em', $assigned)
                        && ! array_key_exists('username', $assigned)
                        && ! array_key_exists('ph', $assigned);
                })
            );
    }

    public function test_later_reviewer_can_track_the_current_reviewer_for_each_competency(): void
    {
        $firstReviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
            'title' => 'นาย',
            'name' => 'ผู้ประเมินลำดับหนึ่ง',
        ]);
        $secondReviewer = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($employee, [
            1 => $firstReviewer->id,
            2 => $secondReviewer->id,
        ]);
        $competencyId = $this->competencyId('CC-TRACKING');
        $assessment = $this->assessment($employee, $competencyId, 'self_submitted');
        $assessment->forceFill(['last_draft_saved_at' => now()])->save();

        $this->actingAs($secondReviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->where('assessmentApprovalModule.enabled', true)
                ->where('assessmentApprovalModule.pendingCount', 0)
                ->where('assessmentApprovalModule.items.0.reviewStep', 2)
                ->where('assessmentApprovalModule.items.0.competencies', [])
                ->where('assessmentApprovalModule.items.0.allCompetencies.0.competencyId', $competencyId)
                ->where('assessmentApprovalModule.items.0.allCompetencies.0.workflow.key', 'pending_review')
                ->where('assessmentApprovalModule.items.0.allCompetencies.0.workflow.currentStep', 1)
                ->where('assessmentApprovalModule.items.0.allCompetencies.0.workflow.currentReviewerId', $firstReviewer->id)
                ->where('assessmentApprovalModule.items.0.allCompetencies.0.workflow.currentReviewerName', 'นายผู้ประเมินลำดับหนึ่ง')
                ->where('assessmentApprovalModule.items.0.allCompetencies.0.workflow.timeline.1.state', 'active')
                ->where('assessmentApprovalModule.items.0.allCompetencies.0.workflow.timeline.2.state', 'waiting')
            );
    }

    public function test_division_head_receives_every_assigned_member_even_before_their_review_step(): void
    {
        $firstReviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $divisionHead = User::factory()->create(['role_id' => $this->roleId('division_head')]);
        $employees = User::factory()->count(2)->create(['role_id' => $this->roleId('employee')]);

        foreach ($employees as $employee) {
            $this->assignAssessmentReviewers($employee, [1 => $firstReviewer->id, 3 => $divisionHead->id]);
        }

        $this->actingAs($divisionHead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Super/Dashboard')
                ->where('assessmentApprovalModule.enabled', true)
                ->where('assessmentApprovalModule.pendingCount', 0)
                ->has('assessmentApprovalModule.items', 2)
                ->has('users', 3)
                ->where('assessmentApprovalModule.items.0.isPending', false)
                ->where('assessmentApprovalModule.items.1.isPending', false)
            );
    }

    public function test_later_reviewer_receives_unsubmitted_position_competencies_alongside_saved_results(): void
    {
        $firstReviewer = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $divisionHead = User::factory()->create(['role_id' => $this->roleId('division_head')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $this->assignAssessmentReviewers($employee, [1 => $firstReviewer->id, 3 => $divisionHead->id]);

        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายทดสอบ', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId, 'name' => 'กลุ่มงานทดสอบ', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId, 'name' => 'ตำแหน่งทดสอบ', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $employee->forceFill(['position_id' => $positionId])->save();

        $savedCompetencyId = $this->competencyId('CC-SAVED');
        $unsubmittedCompetencyId = $this->competencyId('FC1-UNSUBMITTED');
        foreach ([$savedCompetencyId, $unsubmittedCompetencyId] as $competencyId) {
            DB::table('position_competencies')->insert([
                'assessment_round_id' => $this->assessmentRoundId(),
                'position_id' => $positionId,
                'competency_id' => $competencyId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->assessment($employee, $savedCompetencyId, 'self_submitted')
            ->forceFill(['last_draft_saved_at' => now()])->save();

        $this->actingAs($divisionHead)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Super/Dashboard')
                ->where('assessmentApprovalModule.items.0.employeeId', $employee->id)
                ->has('assessmentApprovalModule.items.0.allCompetencies', 1)
                ->has('assessmentApprovalModule.items.0.assignedCompetencies', 2)
                ->where('assessmentApprovalModule.items.0.nextStatus', 'approved')
            );
    }

    public function test_self_assessment_with_only_third_evaluator_is_sent_to_third_step(): void
    {
        $dean = User::factory()->create([
            'role_id' => $this->roleId('dean'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($employee, [
            3 => $dean->id,
        ]);
        $competencyId = $this->competencyId();

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $competencyId,
                'checked_indicators' => [],
                'score' => 0,
                'note' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'status' => 'dept_evaluated',
        ]);
        $this->assertDatabaseHas('competency_gaps', [
            'competency_id' => $competencyId,
            'status' => 'dept_evaluated',
        ]);
    }

    public function test_approval_skips_missing_second_evaluator_and_sends_to_third_step(): void
    {
        $firstReviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $thirdReviewer = User::factory()->create([
            'role_id' => $this->roleId('dean'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($employee, [
            1 => $firstReviewer->id,
            3 => $thirdReviewer->id,
        ]);
        $competencyId = $this->competencyId();
        $assessment = $this->assessment($employee, $competencyId, 'self_submitted');

        $this->actingAs($firstReviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'dept_evaluated',
        ]);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'status' => 'dept_evaluated',
        ]);

        $assessment->forceFill(['last_draft_saved_at' => now()])->save();

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.reviewerId', $firstReviewer->id)
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.reviewStep', 1)
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.decision', 'approved')
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.comment', '')
            );
    }

    public function test_third_evaluator_approval_completes_assessment(): void
    {
        $thirdReviewer = User::factory()->create([
            'role_id' => $this->roleId('dean'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($employee, [
            3 => $thirdReviewer->id,
        ]);
        $competencyId = $this->competencyId();
        $assessment = $this->assessment($employee, $competencyId, 'dept_evaluated');

        $this->actingAs($thirdReviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'status' => 'approved',
        ]);
    }

    public function test_final_approval_creates_missing_gap_with_expected_and_actual_levels(): void
    {
        $thirdReviewer = User::factory()->create([
            'role_id' => $this->roleId('dean'),
        ]);
        [$positionId, $levelId, $competencyId] = $this->positionLevelAndCompetencyForGap('CC-FINAL-GAP');
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'workline' => 'Test Workline',
            'position' => 'Test Position',
            'position_id' => $positionId,
            'level' => 'Test Level',
            'level_id' => $levelId,
        ]);
        $this->assignAssessmentReviewers($employee, [
            3 => $thirdReviewer->id,
        ]);
        $assessment = Assessment::create([
            'user_id' => $employee->id,
            'competency_id' => $competencyId,
            'assessment_round_id' => $this->assessmentRoundId(),
            'score' => 2,
            'note' => '',
            'status' => 'dept_evaluated',
        ]);

        $this->assertDatabaseMissing('competency_gaps', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
        ]);

        $this->actingAs($thirdReviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
            ])
            ->assertSessionHasNoErrors();

        $gap = DB::table('competency_gaps')
            ->where('assessment_id', $assessment->id)
            ->where('competency_id', $competencyId)
            ->first();

        $this->assertNotNull($gap);
        $this->assertSame('approved', $gap->status);
        $this->assertEquals(3, $gap->expected_level);
        $this->assertEquals(2, $gap->actual_level);
        $this->assertEquals(-1, $gap->gap);
        $this->assertTrue((bool) $gap->requires_idp);

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentUserCompetencyGaps.0.competencyId', $competencyId)
                ->where('currentUserCompetencyGaps.0.expected', 3)
                ->where('currentUserCompetencyGaps.0.actual', 2)
                ->where('currentUserCompetencyGaps.0.gap', -1)
                ->where('currentUserCompetencyGaps.0.status', 'approved')
            );
    }

    public function test_dynamic_reviewer_chain_can_continue_past_three_steps(): void
    {
        $reviewers = collect(range(1, 4))
            ->map(fn () => User::factory()->create([
                'role_id' => $this->roleId('supervisor'),
            ]));
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId('CC-DYNAMIC');
        $assessment = $this->assessment($employee, $competencyId, 'self_submitted');

        $this->assignAssessmentReviewers($employee, $reviewers
            ->values()
            ->mapWithKeys(fn (User $reviewer, int $index): array => [$index + 1 => $reviewer->id])
            ->all());

        foreach ([
            0 => 'unit_evaluated',
            1 => 'dept_evaluated',
            2 => 'review_step_4',
            3 => 'approved',
        ] as $reviewerIndex => $expectedStatus) {
            $this->actingAs($reviewers[$reviewerIndex])
                ->post(route('assessments.approve'), [
                    'user_id' => $employee->id,
                    'competency_id' => $competencyId,
                ])
                ->assertSessionHasNoErrors();

            $this->assertDatabaseHas('assessments', [
                'id' => $assessment->id,
                'status' => $expectedStatus,
            ]);
            $this->assertDatabaseHas('competency_gaps', [
                'assessment_id' => $assessment->id,
                'competency_id' => $competencyId,
                'status' => $expectedStatus,
            ]);
        }
    }

    public function test_reviewer_can_approve_only_one_competency_without_touching_the_others(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
            'title' => 'นาย',
            'name' => 'หัวหน้าทดสอบ',
            'position' => 'หัวหน้าหน่วย',
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($employee, [
            1 => $reviewer->id,
        ]);
        $firstCompetencyId = $this->competencyId('CC-ONE');
        $secondCompetencyId = $this->competencyId('CC-TWO');
        $firstAssessment = $this->assessment($employee, $firstCompetencyId, 'self_submitted');
        $secondAssessment = $this->assessment($employee, $secondCompetencyId, 'self_submitted');
        $reviewerComment = trim(str_repeat('ผ่านแล้ว พร้อมข้อเสนอแนะเพิ่มเติม ', 12));

        $this->actingAs($reviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $firstCompetencyId,
                'comment' => $reviewerComment,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'id' => $firstAssessment->id,
            'status' => 'approved',
        ]);
        $this->assertDatabaseHas('assessments', [
            'id' => $secondAssessment->id,
            'status' => 'self_submitted',
        ]);
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $firstAssessment->id,
            'competency_id' => $firstCompetencyId,
            'assessor_id' => $reviewer->id,
            'assessor_role' => 'supervisor_1',
            'comment' => $reviewerComment,
            'status' => 'approved',
        ]);

        $firstAssessment->forceFill(['last_draft_saved_at' => now()])->save();

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Employee/Dashboard')
                ->where('currentUserCompetencyGaps.0.reviewerComments.0.reviewerName', 'นายหัวหน้าทดสอบ')
                ->where('currentUserCompetencyGaps.0.reviewerComments.0.reviewerPosition', 'หัวหน้าหน่วย')
                ->where('currentUserCompetencyGaps.0.reviewerComments.0.reviewStep', 1)
                ->where('currentUserCompetencyGaps.0.reviewerComments.0.comment', $reviewerComment)
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.reviewerName', 'นายหัวหน้าทดสอบ')
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.reviewerPosition', 'หัวหน้าหน่วย')
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.reviewStep', 1)
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.decision', 'approved')
                ->where('currentUserCompetencyGaps.0.reviewHistory.0.comment', $reviewerComment)
                ->has('currentUserCompetencyGaps.0.reviewHistory.0.submittedAt')
            );
    }

    public function test_reviewer_can_reject_only_one_competency_without_touching_the_others(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $nextReviewer = User::factory()->create([
            'role_id' => $this->roleId('dept_head'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($employee, [
            1 => $reviewer->id,
            2 => $nextReviewer->id,
        ]);
        $firstCompetencyId = $this->competencyId('CC-REJECT');
        $secondCompetencyId = $this->competencyId('CC-STAY');
        $firstAssessment = $this->assessment($employee, $firstCompetencyId, 'self_submitted');
        $firstAssessment->forceFill(['note' => 'ประเมินตนเองไว้ก่อนส่งหัวหน้า'])->save();
        $secondAssessment = $this->assessment($employee, $secondCompetencyId, 'self_submitted');
        $rejectComment = trim(str_repeat('ควรประเมินใหม่พร้อมเหตุผลละเอียด ', 12));

        $this->actingAs($reviewer)
            ->post(route('assessments.reject'), [
                'user_id' => $employee->id,
                'competency_id' => $firstCompetencyId,
                'comment' => $rejectComment,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessments', [
            'id' => $firstAssessment->id,
            'status' => 'revision_required',
            'note' => 'ประเมินตนเองไว้ก่อนส่งหัวหน้า',
        ]);
        $this->assertDatabaseHas('assessments', [
            'id' => $secondAssessment->id,
            'status' => 'self_submitted',
        ]);
        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $firstAssessment->id,
            'competency_id' => $firstCompetencyId,
            'status' => 'revision_required',
            'rejected_by' => $reviewer->id,
            'reject_comment' => $rejectComment,
        ]);
        $this->assertDatabaseHas('scores', [
            'assessment_id' => $firstAssessment->id,
            'competency_id' => $firstCompetencyId,
            'assessor_id' => $reviewer->id,
            'assessor_role' => 'supervisor_1',
            'comment' => $rejectComment,
            'status' => 'rejected',
        ]);

        $this->actingAs($employee)
            ->get(route('assessments.load', ['competency_id' => $firstCompetencyId]))
            ->assertOk()
            ->assertJsonPath('status', 'revision_required')
            ->assertJsonPath('reject_comment', $rejectComment)
            ->assertJsonPath('reject_reviewer_name', trim(($reviewer->title ?: '').($reviewer->name ?: '')))
            ->assertJsonPath('locked', false);

        $this->actingAs($employee)
            ->postJson(route('assessments.draft'), [
                'competency_id' => $firstCompetencyId,
                'checked_indicators' => [],
                'score' => 0,
                'note' => 'แก้ไขฉบับร่างหลังถูกส่งกลับ',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'revision_required');

        $this->assertDatabaseHas('competency_gaps', [
            'assessment_id' => $firstAssessment->id,
            'competency_id' => $firstCompetencyId,
            'status' => 'revision_required',
        ]);
    }

    public function test_supervisor_approval_mail_uses_intermediate_status_copy(): void
    {
        Mail::fake();

        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $nextReviewer = User::factory()->create([
            'role_id' => $this->roleId('dept_head'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($employee, [
            1 => $reviewer->id,
            2 => $nextReviewer->id,
        ]);
        $competencyId = $this->competencyId('CC-MAIL');
        $this->assessment($employee, $competencyId, 'self_submitted');

        $this->actingAs($reviewer)
            ->post(route('assessments.approve'), [
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
            ])
            ->assertSessionHasNoErrors();

        Mail::assertSent(AssessmentStatusUpdateMail::class, function (AssessmentStatusUpdateMail $mail) {
            if ($mail->status !== 'unit_evaluated') {
                return false;
            }

            $rendered = $mail->render();

            $this->assertStringContainsString('หัวหน้าหน่วยอนุมัติผลการประเมินแล้ว', $rendered);
            $this->assertStringContainsString('รอการตรวจสอบจากหัวหน้างาน', $rendered);
            $this->assertStringNotContainsString('สามารถเริ่มทำแผนพัฒนารายบุคคล (IDP) ได้', $rendered);

            return true;
        });
    }

    public function test_self_assessment_stores_checked_indicators_separately_from_evidence(): void
    {
        $reviewer = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $this->assignAssessmentReviewers($employee, [
            1 => $reviewer->id,
        ]);
        $competencyId = $this->competencyId('CC-CHECKED');
        $checkedKey = $competencyId.':1:0';

        $this->actingAs($employee)
            ->post(route('assessments.save'), [
                'competency_id' => $competencyId,
                'checked_indicators' => [
                    $checkedKey => true,
                    $competencyId.':1:1' => false,
                ],
                'score' => 0.25,
                'note' => 'ประเมินตนเอง',
            ])
            ->assertSessionHasNoErrors();

        $assessment = Assessment::where('user_id', $employee->id)
            ->where('competency_id', $competencyId)
            ->firstOrFail();

        $this->assertDatabaseHas('assessment_indicator_results', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'indicator_key' => $checkedKey,
            'is_checked' => true,
            'checked_by' => $employee->id,
        ]);
        $this->assertDatabaseMissing('assessment_evidences', [
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
        ]);

        $checked = $this->actingAs($employee)
            ->get(route('assessments.load', ['competency_id' => $competencyId]))
            ->assertOk()
            ->json('checked');

        $this->assertTrue($checked[$checkedKey] ?? false);
    }

    private function assessment(User $user, int $competencyId, string $status): Assessment
    {
        $values = [
            'user_id' => $user->id,
            'competency_id' => $competencyId,
            'score' => 0,
            'note' => '',
            'status' => $status,
        ];

        if (Schema::hasColumn('assessments', 'assessment_round_id')) {
            $values['assessment_round_id'] = $this->assessmentRoundId();
        }

        $assessment = Assessment::create($values);

        DB::table('competency_gaps')->insert([
            'assessment_id' => $assessment->id,
            'competency_id' => $competencyId,
            'actual_level' => 0,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $assessment;
    }

    private function competencyId(string $code = 'CC-TEST'): int
    {
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => $code.'-TYPE',
            'full_name' => 'Core Competency',
            'description' => 'Core Competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => $code,
            'name' => 'Test Competency',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function positionLevelAndCompetencyForGap(string $code): array
    {
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'Test Workline',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'Test Family',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'Test Position',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $levelId = DB::table('levels')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'Test Level',
            'expected_level' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $competencyId = $this->competencyId($code);

        DB::table('position_competencies')->insert([
            'assessment_round_id' => $this->assessmentRoundId(),
            'position_id' => $positionId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$positionId, $levelId, $competencyId];
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
    }

    private function assignAssessmentReviewers(User $user, array $reviewerIdsByStep): void
    {
        DB::table('user_reviewer_steps')->where('user_id', $user->id)->where('chain_type', 'assessment')->delete();

        $rows = collect($reviewerIdsByStep)
            ->map(fn (int $reviewerId, int|string $step): array => [
                'user_id' => $user->id,
                'step_order' => (int) $step,
                'reviewer_id' => $reviewerId,
                'chain_type' => 'assessment',
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->filter(fn (array $row): bool => $row['step_order'] > 0 && $row['reviewer_id'] > 0)
            ->values()
            ->all();

        if ($rows !== []) {
            DB::table('user_reviewer_steps')->insert($rows);
        }
    }

    private function assessmentRoundId(): int
    {
        $existing = DB::table('assessment_rounds')->where('is_active', true)->value('id');
        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบ',
            'year' => 2568,
            'self_assess_start' => now()->subMonth()->toDateString(),
            'self_assess_end' => now()->addMonth()->toDateString(),
            'supervisor_assess_end' => now()->addMonths(2)->toDateString(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
