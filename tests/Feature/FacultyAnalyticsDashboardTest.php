<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FacultyAnalyticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_and_dean_receive_the_same_real_faculty_analytics(): void
    {
        $hr = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $dean = User::factory()->create(['role_id' => $this->roleId('dean')]);
        $academic = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'name' => 'บุคลากรสายวิชาการ',
            'workline' => 'สายวิชาการ',
            'department' => 'ภาควิชาวิศวกรรมคอมพิวเตอร์',
            'position' => 'อาจารย์',
        ]);
        $support = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'name' => 'บุคลากรสายสนับสนุน',
            'workline' => 'สายสนับสนุน',
            'department' => 'ฝ่ายบริหาร > งานทรัพยากรบุคคล > หน่วยพัฒนาบุคลากร',
            'position' => 'นักวิชาการศึกษา',
        ]);
        User::factory()->create(['role_id' => $this->roleId('admin')]);

        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบ 2569',
            'year' => 2569,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $competencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-001',
            'name' => 'การมุ่งเน้นผู้เรียนและผู้รับบริการ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $academicAssessmentId = $this->assessment($roundId, $academic->id);
        $supportAssessmentId = $this->assessment($roundId, $support->id);
        $academicGapId = $this->gap($academicAssessmentId, $competencyId, 3, 2, -1);
        $this->gap($supportAssessmentId, $competencyId, 4, 2, -2);

        $idpId = DB::table('idps')->insertGetId([
            'assessment_id' => $academicAssessmentId,
            'user_id' => $academic->id,
            'year' => 2569,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $itemId = DB::table('idp_items')->insertGetId([
            'idp_id' => $idpId,
            'competency_gap_id' => $academicGapId,
            'goal' => 'พัฒนาสมรรถนะ',
            'status' => 'approved',
            'submission_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('idp_activities')->insert([
            'idp_item_id' => $itemId,
            'activity_name' => 'เรียนรู้จากงานจริง',
            'end_date' => now()->subDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([[$hr, 'HR/Dashboard'], [$dean, 'Executive/Dashboard']] as [$viewer, $component]) {
            $this->actingAs($viewer)
                ->get('/dashboard')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('facultyAnalytics.round.name', 'รอบทดสอบ 2569')
                    ->where('facultyAnalytics.round.isActive', true)
                    ->where('facultyAnalytics.summary.totalEmployees', 3)
                    ->where('facultyAnalytics.summary.assessedEmployees', 2)
                    ->where('facultyAnalytics.summary.employeesWithGap', 2)
                    ->where('facultyAnalytics.summary.employeesWithoutGap', 0)
                    ->where('facultyAnalytics.worklines.0.key', 'academic')
                    ->where('facultyAnalytics.worklines.0.totalEmployees', 1)
                    ->where('facultyAnalytics.worklines.0.assessedEmployees', 1)
                    ->where('facultyAnalytics.worklines.0.topCompetencies.0.code', 'CC-001')
                    ->where('facultyAnalytics.worklines.0.topCompetencies.0.peopleCount', 1)
                    ->where('facultyAnalytics.worklines.0.organizations.0.label', 'ภาควิชาวิศวกรรมคอมพิวเตอร์')
                    ->where('facultyAnalytics.worklines.0.organizations.0.passedEmployees', 0)
                    ->where('facultyAnalytics.worklines.0.organizations.0.failedEmployees', 1)
                    ->where('facultyAnalytics.worklines.0.organizations.0.topCompetencies.0.code', 'CC-001')
                    ->where('facultyAnalytics.worklines.0.organizations.0.topCompetencies.0.peopleCount', 1)
                    ->where('facultyAnalytics.worklines.1.key', 'support')
                    ->where('facultyAnalytics.worklines.1.organizations.0.levelLabel', 'ฝ่าย')
                    ->where('facultyAnalytics.worklines.1.organizations.1.levelLabel', 'งาน')
                    ->where('facultyAnalytics.worklines.1.organizations.2.levelLabel', 'หน่วย')
                    ->where('facultyAnalytics.worklines.1.organizations.2.label', 'หน่วยพัฒนาบุคลากร')
                    ->where('facultyAnalytics.worklines.1.organizations.2.failedEmployees', 1)
                    ->where('facultyAnalytics.worklines.1.organizations.2.topCompetencies.0.peopleCount', 1)
                    ->where('facultyAnalytics.assessmentHeatmap.0.code', 'CC-001')
                    ->where('facultyAnalytics.assessmentHeatmap.0.academic.people.0.expectedLevel', 3)
                    ->where('facultyAnalytics.assessmentHeatmap.0.academic.people.0.actualLevel', 2)
                    ->where('facultyAnalytics.assessmentHeatmap.0.academic.people.0.gap', -1)
                    ->where('facultyAnalytics.heatmap.0.code', 'CC-001')
                    ->where('facultyAnalytics.heatmap.0.faculty.peopleCount', 2)
                    ->where('facultyAnalytics.heatmap.0.faculty.severeCount', 1)
                    ->where('facultyAnalytics.idpSummary.requiredEmployees', 2)
                    ->where('facultyAnalytics.idpSummary.startedEmployees', 1)
                    ->where('facultyAnalytics.idpSummary.notStartedEmployees', 1)
                    ->where('facultyAnalytics.idpSummary.totalItems', 2)
                    ->where('facultyAnalytics.idpSummary.startedItems', 1)
                    ->where('facultyAnalytics.idpSummary.states.developing', 1)
                    ->where('facultyAnalytics.idpSummary.states.not_started', 1)
                    ->where('facultyAnalytics.idpByCompetency.0.states.developing', 1)
                    ->where('facultyAnalytics.idpByCompetency.0.states.not_started', 1)
                    ->where('facultyAnalytics.idpDetails', fn ($items): bool => $items
                        ->contains(fn ($item): bool => $item['userId'] === $academic->id
                            && $item['competencyCode'] === 'CC-001'
                            && $item['goal'] === 'พัฒนาสมรรถนะ'
                            && $item['canReview'] === false
                            && $item['activities'][0]['name'] === 'เรียนรู้จากงานจริง')
                        && $items->contains(fn ($item): bool => $item['userId'] === $support->id
                            && $item['planStatus'] === 'not_started'
                            && $item['activities'] === []))
                    ->where('facultyAnalytics.urgentCompetencies.0.overdueCount', 1));
        }
    }

    public function test_faculty_overview_can_select_a_round_and_only_uses_that_rounds_results(): void
    {
        $viewer = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายวิชาการ',
        ]);
        $previousRoundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบปี 2568',
            'year' => 2568,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activeRoundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบปี 2569',
            'year' => 2569,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $previousCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-OLD',
            'name' => 'สมรรถนะรอบเดิม',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $activeCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-NEW',
            'name' => 'สมรรถนะรอบใหม่',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->gap($this->assessment($previousRoundId, $employee->id), $previousCompetencyId, 3, 2, -1);
        $this->gap($this->assessment($activeRoundId, $employee->id), $activeCompetencyId, 4, 2, -2);

        $this->actingAs($viewer)
            ->get(route('dashboard', ['assessment_round_id' => $previousRoundId]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('HR/Dashboard')
                ->where('facultyAnalytics.round.id', $previousRoundId)
                ->where('facultyAnalytics.round.name', 'รอบปี 2568')
                ->where('facultyAnalytics.round.isActive', false)
                ->where('facultyAnalytics.rounds.0.id', $activeRoundId)
                ->where('facultyAnalytics.rounds.1.id', $previousRoundId)
                ->has('facultyAnalytics.heatmap', 1)
                ->where('facultyAnalytics.heatmap.0.code', 'CC-OLD')
                ->where('activeCycleName', 'รอบปี 2569'));
    }

    public function test_faculty_analytics_uses_the_latest_round_when_no_round_is_active(): void
    {
        $viewer = User::factory()->create(['role_id' => $this->roleId('dean')]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายวิชาการ',
        ]);
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบล่าสุด 2569',
            'year' => 2569,
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assessment($roundId, $employee->id);

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Executive/Dashboard')
                ->where('facultyAnalytics.round.name', 'รอบล่าสุด 2569')
                ->where('facultyAnalytics.round.isActive', false)
                ->where('facultyAnalytics.summary.assessedEmployees', 1));
    }

    public function test_employee_is_counted_as_assessed_only_when_every_competency_is_approved(): void
    {
        $viewer = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'workline' => 'สายสนับสนุน',
        ]);
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบความครบถ้วน',
            'year' => 2570,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $firstCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-COMPLETE-1',
            'name' => 'สมรรถนะที่อนุมัติแล้ว',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $secondCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => 'CC-COMPLETE-2',
            'name' => 'สมรรถนะที่ยังไม่อนุมัติ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assessment($roundId, $employee->id);
        DB::table('assessments')->insert([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'competency_id' => $secondCompetencyId,
            'status' => 'self_submitted',
            'score' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('assessments')
            ->where('assessment_round_id', $roundId)
            ->where('user_id', $employee->id)
            ->whereNull('competency_id')
            ->update(['competency_id' => $firstCompetencyId]);

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('facultyAnalytics.summary.totalEmployees', 2)
                ->where('facultyAnalytics.summary.assessedEmployees', 0));
    }

    public function test_unselected_fc_drafts_do_not_prevent_employee_from_being_counted_as_assessed(): void
    {
        $viewer = User::factory()->create(['role_id' => $this->roleId('hr')]);
        $worklineId = DB::table('worklines')->insertGetId([
            'name' => 'สายวิชาการทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $jobFamilyId = DB::table('job_families')->insertGetId([
            'workline_id' => $worklineId,
            'name' => 'กลุ่มงานวิชาการทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $positionId = DB::table('positions')->insertGetId([
            'job_family_id' => $jobFamilyId,
            'name' => 'อาจารย์ทดสอบ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
            'position_id' => $positionId,
            'position' => 'อาจารย์ทดสอบ',
            'workline' => 'สายวิชาการ',
        ]);
        $roundId = DB::table('assessment_rounds')->insertGetId([
            'name' => 'รอบทดสอบ FC',
            'year' => 2570,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fcTypeId = DB::table('competency_types')->insertGetId([
            'code' => 'FC1',
            'full_name' => 'Functional Competency',
            'description' => 'สมรรถนะตามหน้าที่',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $selectedCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $fcTypeId,
            'code' => 'FC-SELECTED',
            'name' => 'FC ที่เลือก',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unselectedCompetencyId = DB::table('competencies')->insertGetId([
            'competency_type_id' => $fcTypeId,
            'code' => 'FC-UNSELECTED',
            'name' => 'FC ที่ไม่ได้เลือก',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('position_fc_selection_rules')->insert([
            'assessment_round_id' => $roundId,
            'position_id' => $positionId,
            'required_fc_count' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $selectionId = DB::table('fc_topic_selections')->insertGetId([
            'assessment_round_id' => $roundId,
            'user_id' => $employee->id,
            'position_id' => $positionId,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('fc_topic_selection_items')->insert([
            'fc_topic_selection_id' => $selectionId,
            'competency_id' => $selectedCompetencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ([[$selectedCompetencyId, 'approved'], [$unselectedCompetencyId, 'draft']] as [$competencyId, $status]) {
            DB::table('assessments')->insert([
                'assessment_round_id' => $roundId,
                'user_id' => $employee->id,
                'competency_id' => $competencyId,
                'status' => $status,
                'score' => $status === 'approved' ? 2 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('facultyAnalytics.summary.totalEmployees', 2)
                ->where('facultyAnalytics.summary.assessedEmployees', 1));
    }

    private function assessment(int $roundId, int $userId): int
    {
        return (int) DB::table('assessments')->insertGetId([
            'assessment_round_id' => $roundId,
            'user_id' => $userId,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function gap(int $assessmentId, int $competencyId, int $expected, int $actual, int $gap): int
    {
        return (int) DB::table('competency_gaps')->insertGetId([
            'assessment_id' => $assessmentId,
            'competency_id' => $competencyId,
            'expected_level' => $expected,
            'actual_level' => $actual,
            'gap' => $gap,
            'requires_idp' => true,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')
            ->where('key', $key)
            ->orWhere('role_key', $key)
            ->value('id');
    }
}
