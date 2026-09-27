<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_role_sees_manager_dashboard_with_database_summary(): void
    {
        $manager = User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'dean')->value('id'),
        ]);

        User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'employee')->value('id'),
        ]);

        $response = $this->actingAs($manager)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Executive/Dashboard')
            ->where('managerSummary.totalUsers', 2)
            ->where('managerSummary.evaluatedUsers', 0)
            ->where('managerSummary.passedUsers', 0)
            ->where('managerSummary.failedUsers', 0)
            ->where('managerSummary.trainingNeeds', 0)
            ->where('managerSummary.pendingAssessmentApprovals', 0)
            ->where('managerSummary.pendingIdpApprovals', 0)
            ->where('managerSummary.source', 'database')
            ->where('activeCycleName', 'รอบประเมินปัจจุบัน')
            ->where('departmentRows', [])
            ->where('problemCompetencyRows', [])
            ->where('idpProgressRows', [])
            ->where('idpNoProgressRows', [])
            ->where('trainingNeedRows', [])
            ->where('assessmentApprovals', [])
            ->where('idpApprovals', [])
            ->where('assessmentApprovalModule.enabled', false)
            ->where('idpReviewModule.enabled', false)
            ->missing('currentUserCompetencies')
            ->missing('currentUserFcTopicSelection')
            ->missing('currentUserIdp')
        );
    }

    public function test_dean_sees_review_menu_data_only_when_assigned_in_runtime_chain(): void
    {
        $dean = User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'dean')->value('id'),
        ]);
        $employee = User::factory()->create([
            'role_id' => (int) DB::table('roles')->where('key', 'employee')->value('id'),
        ]);

        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $dean->id,
            'step_order' => 1,
            'chain_type' => 'assessment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($dean)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Executive/Dashboard')
                ->where('assessmentApprovalModule.enabled', true)
                ->where('idpReviewModule.enabled', false)
                ->where('reviewerTeamUsers', fn ($users): bool => collect($users)
                    ->pluck('db_id')->sort()->values()->all() === collect([
                        $dean->id,
                        $employee->id,
                    ])->sort()->values()->all())
            );
    }
}
