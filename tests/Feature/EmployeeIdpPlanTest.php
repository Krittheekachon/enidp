<?php

namespace Tests\Feature;

use App\Mail\IdpSubmittedMail;
use App\Models\Assessment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeIdpPlanTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_NOTIFICATION_RECIPIENT = 'krittheekachon.s@kkumail.com';

    public function test_employee_can_save_one_competency_plan_with_multiple_activities(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId();
        $assessment = $this->assessment($employee, $competencyId);
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
        $methodTypeId = DB::table('learning_method_types')->insertGetId([
            'key' => 'experiential-learning',
            'label' => 'Experiential Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $projectToolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'experiential',
            'title' => 'การมอบหมายงานโครงการ',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $rotationToolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'experiential',
            'title' => 'การหมุนเวียนงาน',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'ทำงานให้ตรงตามมาตรฐาน',
                    'successCriteria' => '',
                    'activities' => [
                        [
                            'methodKey' => 'experiential-learning',
                            'developmentToolId' => $projectToolId,
                            'learningCatalogId' => null,
                            'activityName' => 'ชื่อที่ผู้ใช้แก้เองต้องไม่ถูกบันทึก',
                            'activityDescription' => 'ฝึกทำงานจริง',
                            'documentReferenceNumber' => 'กจ.01/2569',
                            'weightPercent' => 70,
                            'startDate' => '2026-06-16',
                            'endDate' => '2026-07-16',
                        ],
                        [
                            'methodKey' => 'experiential-learning',
                            'developmentToolId' => $rotationToolId,
                            'learningCatalogId' => null,
                            'activityName' => 'ชื่อปลอมอีกรายการ',
                            'activityDescription' => 'หมุนเวียนงาน',
                            'weightPercent' => 30,
                            'startDate' => '2026-07-17',
                            'endDate' => '2026-08-16',
                        ],
                    ],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idps', [
            'user_id' => $employee->id,
            'year' => 2568,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('idp_items', [
            'competency_gap_id' => $gapId,
            'goal' => 'ทำงานให้ตรงตามมาตรฐาน',
        ]);
        $this->assertDatabaseCount('idp_items', 1);
        $this->assertDatabaseCount('idp_activities', 2);
        $this->assertDatabaseHas('idp_activities', [
            'activity_name' => 'การมอบหมายงานโครงการ',
            'method_type_id' => $methodTypeId,
            'document_reference_number' => 'กจ.01/2569',
            'weight_percent' => 70,
        ]);
        $this->assertDatabaseHas('idp_activities', [
            'activity_name' => 'การหมุนเวียนงาน',
            'weight_percent' => 30,
        ]);
    }

    public function test_employee_can_save_activity_form_details(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId('CC-FORM');
        $assessment = $this->assessment($employee, $competencyId);
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
        DB::table('learning_method_types')->insert([
            'key' => 'social-learning',
            'label' => 'Social Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $toolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'social',
            'title' => 'การสอนงาน (Coaching)',
            'form_code' => 'form_5_coaching',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'พัฒนาการสื่อสารกับผู้รับบริการ',
                    'successCriteria' => 'ให้บริการได้ตามมาตรฐาน',
                    'activities' => [[
                        'methodKey' => 'social-learning',
                        'developmentToolId' => $toolId,
                        'learningCatalogId' => null,
                        'activityName' => '03 · การสอนงาน (Coaching)',
                        'documentReferenceNumber' => 'DOC-5',
                        'weightPercent' => 100,
                        'formCode' => 'form_5_coaching',
                        'formDetails' => [
                            '_saved' => true,
                            'detail' => [
                                'coachType' => 'ผู้เชี่ยวชาญ',
                                'coachExpertName' => 'ผู้เชี่ยวชาญทดสอบ',
                            ],
                            'planRows' => [[
                                'topic' => 'ฝึกสื่อสารกับผู้รับบริการ',
                                'coachingApproaches' => ['A', 'C'],
                                'developmentStart' => '2026-07-01',
                                'developmentEnd' => '2026-09-30',
                                'sessionCount' => 6,
                                'sessionDuration' => 'ครั้งละ 2 ชั่วโมง',
                                'developmentGoal' => 'สื่อสารกับผู้รับบริการได้ตามมาตรฐาน',
                                'developmentApproach' => 'สาธิต ฝึกปฏิบัติ และให้ข้อเสนอแนะ',
                            ]],
                        ],
                    ]],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $activity = DB::table('idp_activities')
            ->where('activity_name', 'การสอนงาน (Coaching)')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('form_5_coaching', $activity->form_code);
        $this->assertNull($activity->document_reference_number);
        $this->assertSame('2026-07-01', $activity->start_date);
        $this->assertSame('2026-09-30', $activity->end_date);
        $formDetails = json_decode($activity->form_details, true);
        $this->assertSame(
            'ฝึกสื่อสารกับผู้รับบริการ',
            $formDetails['planRows'][0]['topic'],
        );
        $this->assertSame(['A', 'C'], $formDetails['planRows'][0]['coachingApproaches']);
        $this->assertSame('ผู้เชี่ยวชาญทดสอบ', $formDetails['detail']['coachExpertName']);
    }

    public function test_project_assignment_and_ojt_use_the_reworked_form_fields(): void
    {
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $competencyId = $this->competencyId('CC-PROJECT');
        $assessment = $this->assessment($employee, $competencyId);
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
        DB::table('learning_method_types')->insert([
            'key' => 'experiential-learning',
            'label' => 'Experiential Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $projectToolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'experiential', 'title' => 'การมอบหมายงานโครงการ',
            'form_code' => 'form_3_project_assignment', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $ojtToolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'experiential', 'title' => 'การเรียนรู้จากการปฏิบัติงานจริง',
            'form_code' => 'form_4_ojt', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($employee)->post(route('employee.idp.draft'), [
            'items' => [[
                'competencyGapId' => $gapId,
                'goal' => 'พัฒนาการบริหารโครงการ',
                'successCriteria' => '',
                'activities' => [[
                    'methodKey' => 'experiential-learning',
                    'developmentToolId' => $projectToolId,
                    'activityName' => 'การมอบหมายงานโครงการ',
                    'documentReferenceNumber' => 'ต้องไม่ถูกบันทึก',
                    'weightPercent' => 50,
                    'startDate' => '2026-09-01',
                    'endDate' => '2026-10-31',
                    'formCode' => 'form_3_project_assignment',
                    'formDetails' => [
                        '_saved' => true,
                        'planRows' => [
                            [
                                'assignmentTopic' => 'ปรับปรุงกระบวนการบริการ',
                                'developmentGoal' => 'บริหารโครงการได้ครบวงจร',
                                'developmentApproach' => 'วางแผนและติดตามผลรายสัปดาห์',
                                'developmentStart' => '2026-09-01',
                                'developmentEnd' => '2026-10-31',
                            ],
                            [
                                'assignmentTopic' => 'จัดทำคู่มือบริการ',
                                'developmentGoal' => 'ถ่ายทอดกระบวนการทำงานได้',
                                'developmentApproach' => 'รวบรวมและทดสอบขั้นตอนกับทีม',
                                'developmentStart' => '2026-10-01',
                                'developmentEnd' => '2026-11-30',
                            ],
                        ],
                    ],
                ], [
                    'methodKey' => 'experiential-learning',
                    'developmentToolId' => $ojtToolId,
                    'activityName' => 'การเรียนรู้จากการปฏิบัติงานจริง',
                    'documentReferenceNumber' => 'ต้องไม่ถูกบันทึก',
                    'weightPercent' => 50,
                    'startDate' => '2026-12-01',
                    'endDate' => '2027-01-31',
                    'formCode' => 'form_4_ojt',
                    'formDetails' => [
                        '_saved' => true,
                        'detail' => [
                            'trainerType' => 'ผู้เชี่ยวชาญ',
                            'trainerExpertName' => 'ผู้เชี่ยวชาญด้านข้อมูล',
                        ],
                        'planRows' => [[
                            'skillTopic' => 'ฝึกวิเคราะห์ข้อมูลบริการ',
                            'developmentStart' => '2026-12-01',
                            'developmentEnd' => '2027-01-31',
                            'hours' => 12,
                            'developmentGoal' => 'วิเคราะห์ปัญหาได้ด้วยตนเอง',
                            'developmentApproach' => 'ฝึกจากข้อมูลจริงร่วมกับผู้เชี่ยวชาญ',
                        ]],
                    ],
                ]],
            ]],
        ])->assertSessionHasNoErrors();

        $activity = DB::table('idp_activities')->where('form_code', 'form_3_project_assignment')->first();

        $this->assertNotNull($activity);
        $this->assertNull($activity->document_reference_number);
        $this->assertSame('2026-09-01', $activity->start_date);
        $this->assertSame('2026-11-30', $activity->end_date);
        $this->assertCount(2, json_decode($activity->form_details, true)['planRows']);
        $this->assertSame(
            'ปรับปรุงกระบวนการบริการ',
            json_decode($activity->form_details, true)['planRows'][0]['assignmentTopic'],
        );

        $ojtActivity = DB::table('idp_activities')->where('form_code', 'form_4_ojt')->first();
        $this->assertNotNull($ojtActivity);
        $this->assertNull($ojtActivity->document_reference_number);
        $this->assertSame(
            'ผู้เชี่ยวชาญด้านข้อมูล',
            json_decode($ojtActivity->form_details, true)['detail']['trainerExpertName'],
        );
        $this->assertSame('2026-12-01', $ojtActivity->start_date);
        $this->assertSame('2027-01-31', $ojtActivity->end_date);
        $this->assertSame(12, json_decode($ojtActivity->form_details, true)['planRows'][0]['hours']);
    }

    public function test_employee_can_save_reworked_mentoring_form(): void
    {
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $competencyId = $this->competencyId('CC-MENTORING');
        $assessment = $this->assessment($employee, $competencyId);
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
        DB::table('learning_method_types')->insert([
            'key' => 'social-learning',
            'label' => 'Social Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $toolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'social', 'title' => 'การเป็นพี่เลี้ยง',
            'form_code' => 'form_6_mentoring', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($employee)->post(route('employee.idp.draft'), [
            'items' => [[
                'competencyGapId' => $gapId,
                'goal' => 'พัฒนาทักษะการให้คำปรึกษา',
                'successCriteria' => '',
                'activities' => [[
                    'methodKey' => 'social-learning',
                    'developmentToolId' => $toolId,
                    'activityName' => 'การเป็นพี่เลี้ยง',
                    'documentReferenceNumber' => 'ต้องไม่ถูกบันทึก',
                    'weightPercent' => 100,
                    'startDate' => '2027-02-01',
                    'endDate' => '2027-03-31',
                    'formCode' => 'form_6_mentoring',
                    'formDetails' => [
                        '_saved' => true,
                        'detail' => [
                            'mentorType' => 'ผู้เชี่ยวชาญ',
                            'mentorExpertName' => 'ผู้เชี่ยวชาญด้านการให้คำปรึกษา',
                        ],
                        'planRows' => [[
                            'skillTopic' => 'ฝึกการให้คำปรึกษาแก่ทีมงาน',
                            'technique' => 'สังเกตการณ์และสะท้อนผล',
                            'developmentStart' => '2027-02-01',
                            'developmentEnd' => '2027-03-31',
                            'sessionCount' => 8,
                            'sessionDuration' => 'ครั้งละ 1 ชั่วโมง',
                            'developmentGoal' => 'ให้คำปรึกษาได้อย่างเป็นระบบ',
                        ]],
                    ],
                ]],
            ]],
        ])->assertSessionHasNoErrors();

        $activity = DB::table('idp_activities')->where('form_code', 'form_6_mentoring')->first();
        $this->assertNotNull($activity);
        $details = json_decode($activity->form_details, true);
        $this->assertNull($activity->document_reference_number);
        $this->assertSame('2027-02-01', $activity->start_date);
        $this->assertSame('2027-03-31', $activity->end_date);
        $this->assertSame('ผู้เชี่ยวชาญด้านการให้คำปรึกษา', $details['detail']['mentorExpertName']);
        $this->assertSame('สังเกตการณ์และสะท้อนผล', $details['planRows'][0]['technique']);
    }

    public function test_employee_must_complete_required_idp_fields_before_submit(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId('CC-SUBMIT');
        $assessment = $this->assessment($employee, $competencyId);
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

        $this->actingAs($employee)
            ->post(route('employee.idp.submit'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => '',
                    'successCriteria' => '',
                    'activities' => [],
                ]],
            ])
            ->assertSessionHasErrors();
    }

    public function test_employee_cannot_choose_formal_catalog_from_another_competency(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId('CC-FORMAL');
        $otherCompetencyId = $this->competencyId('CC-OTHER');
        $assessment = $this->assessment($employee, $competencyId);
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
        $formalMethodId = DB::table('learning_method_types')->insertGetId([
            'key' => 'formal-learning',
            'label' => 'Formal Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'name' => 'หลักสูตรคนละสมรรถนะ',
            'method_type_id' => $formalMethodId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId,
            'competency_id' => $otherCompetencyId,
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'พัฒนาสมรรถนะ',
                    'successCriteria' => 'ทำงานได้ตามเกณฑ์',
                    'activities' => [[
                        'methodKey' => 'formal-learning',
                        'learningCatalogId' => $catalogId,
                        'activityName' => 'หลักสูตรคนละสมรรถนะ',
                        'weightPercent' => 100,
                        'startDate' => '2026-06-16',
                        'endDate' => '2026-07-16',
                    ]],
                ]],
            ])
            ->assertSessionHasErrors('items.0.activities.0.learningCatalogId');
    }

    public function test_training_form_keeps_catalog_snapshot_and_allows_e_learning_without_code_or_cost(): void
    {
        $supervisor = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $supervisor->id,
            'step_order' => 1,
            'chain_type' => 'idp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $competencyId = $this->competencyId('CC-TRAINING');
        $gapId = $this->approvedGap($employee, $competencyId);
        $formalMethodId = DB::table('learning_method_types')->insertGetId([
            'key' => 'formal-learning',
            'label' => 'Formal Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'code' => null,
            'name' => 'บทเรียนออนไลน์เพื่อพัฒนาทักษะ',
            'method_type_id' => $formalMethodId,
            'delivery_type' => 'e_learning',
            'source_type' => 'internal',
            'cost' => null,
            'hours' => 6,
            'description' => 'คำอธิบายจาก Learning Catalog',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.submit'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'พัฒนาทักษะตามสมรรถนะ',
                    'successCriteria' => 'ผ่านตามเกณฑ์ที่กำหนด',
                    'activities' => [[
                        'methodKey' => 'formal-learning',
                        'developmentToolId' => null,
                        'learningCatalogId' => $catalogId,
                        'activityName' => 'บทเรียนออนไลน์เพื่อพัฒนาทักษะ',
                        'activityDescription' => 'คำอธิบายจาก Learning Catalog',
                        'weightPercent' => 100,
                        'startDate' => '2026-09-01',
                        'endDate' => '2026-09-30',
                        'formCode' => 'form_10_training',
                        'formDetails' => [
                            '_saved' => true,
                            'planRows' => [[
                                'trainingType' => 'e-Learning',
                                'courseCode' => '',
                                'courseName' => 'บทเรียนออนไลน์เพื่อพัฒนาทักษะ',
                                'courseDescription' => 'คำอธิบายจาก Learning Catalog',
                                'hours' => 6,
                                'cost' => null,
                                'developmentStart' => '2026-09-01',
                                'developmentEnd' => '2026-09-30',
                                'developmentGoal' => 'นำความรู้ไปใช้ในการทำงาน',
                                'additionalDetails' => 'ต้องการแจ้งหัวหน้าให้ทราบล่วงหน้า',
                            ]],
                        ],
                    ]],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $activity = DB::table('idp_activities')->where('learning_catalog_id', $catalogId)->first();
        $this->assertNotNull($activity);
        $details = json_decode($activity->form_details, true);
        $this->assertSame('คำอธิบายจาก Learning Catalog', $details['planRows'][0]['courseDescription']);
        $this->assertSame(6, $details['planRows'][0]['hours']);
        $this->assertNull($details['planRows'][0]['cost']);
        $this->assertSame('ต้องการแจ้งหัวหน้าให้ทราบล่วงหน้า', $details['planRows'][0]['additionalDetails']);
    }

    public function test_training_ignores_tampered_catalog_fields_and_allows_missing_system_hours(): void
    {
        $supervisor = User::factory()->create(['role_id' => $this->roleId('supervisor')]);
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id, 'reviewer_id' => $supervisor->id,
            'step_order' => 1, 'chain_type' => 'idp',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $competencyId = $this->competencyId('CC-SAFE-CATALOG');
        $gapId = $this->approvedGap($employee, $competencyId);
        $formalMethodId = DB::table('learning_method_types')->insertGetId([
            'key' => 'formal-learning', 'label' => 'Formal Learning', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'code' => 'SYSTEM-001', 'name' => 'หลักสูตรจากระบบ',
            'method_type_id' => $formalMethodId, 'delivery_type' => 'e_learning',
            'source_type' => 'internal', 'hours' => null, 'cost' => null,
            'expected_levels' => json_encode([3]), 'description' => 'รายละเอียดจากระบบ',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId, 'competency_id' => $competencyId,
        ]);

        $this->actingAs($employee)->post(route('employee.idp.submit-item'), [
            'item' => [
                'competencyGapId' => $gapId,
                'goal' => 'พัฒนาตามแผน',
                'successCriteria' => 'ผ่านตามเกณฑ์',
                'activities' => [[
                    'methodKey' => 'formal-learning',
                    'learningCatalogId' => $catalogId,
                    'activityName' => 'ชื่อที่ปลอมมา',
                    'weightPercent' => 100,
                    'formCode' => 'form_3_project_assignment',
                    'formDetails' => [
                        '_saved' => true,
                        'planRows' => [[
                            'trainingType' => 'In-class Training',
                            'courseCode' => 'HACKED',
                            'courseName' => 'ชื่อที่ปลอมมา',
                            'courseDescription' => 'รายละเอียดที่ปลอมมา',
                            'hours' => 999,
                            'cost' => 999999,
                            'developmentStart' => '2026-09-01',
                            'developmentEnd' => '2026-09-30',
                            'developmentGoal' => 'นำความรู้ไปใช้',
                        ]],
                    ],
                ]],
            ],
        ])->assertSessionHasNoErrors();

        $activity = DB::table('idp_activities')->where('learning_catalog_id', $catalogId)->first();
        $details = json_decode($activity->form_details, true)['planRows'][0];
        $this->assertSame('หลักสูตรจากระบบ', $activity->activity_name);
        $this->assertSame('form_10_training', $activity->form_code);
        $this->assertSame('SYSTEM-001', $details['courseCode']);
        $this->assertSame('หลักสูตรจากระบบ', $details['courseName']);
        $this->assertSame('e-Learning', $details['trainingType']);
        $this->assertNull($details['hours']);
        $this->assertNull($details['cost']);
    }

    public function test_formal_catalog_must_match_the_users_expected_level(): void
    {
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $competencyId = $this->competencyId('CC-LEVEL-CATALOG');
        $gapId = $this->approvedGap($employee, $competencyId);
        $formalMethodId = DB::table('learning_method_types')->insertGetId([
            'key' => 'formal-learning', 'label' => 'Formal Learning', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'name' => 'หลักสูตรเฉพาะระดับสอง', 'method_type_id' => $formalMethodId,
            'delivery_type' => 'e_learning', 'source_type' => 'internal',
            'expected_levels' => json_encode([2]), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId, 'competency_id' => $competencyId,
        ]);

        $payload = [
            'competencyGapId' => $gapId,
            'goal' => 'พัฒนาตามแผน',
            'successCriteria' => 'ผ่านตามเกณฑ์',
            'activities' => [[
                'methodKey' => 'formal-learning', 'learningCatalogId' => $catalogId,
                'weightPercent' => 100, 'formCode' => 'form_10_training',
                'formDetails' => ['_saved' => true, 'planRows' => [[
                    'developmentStart' => '2026-09-01', 'developmentEnd' => '2026-09-30',
                    'developmentGoal' => 'นำความรู้ไปใช้',
                ]]],
            ]],
        ];

        $this->actingAs($employee)
            ->postJson(route('employee.idp.submit-item'), ['item' => $payload])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.activities.0.learningCatalogId');
    }

    public function test_employee_can_submit_one_competency_plan_while_another_remains_draft(): void
    {
        $supervisor = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $supervisor->id,
            'step_order' => 1,
            'chain_type' => 'idp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $firstCompetencyId = $this->competencyId('CC-FIRST');
        $secondCompetencyId = $this->competencyId('CC-SECOND');
        $firstGapId = $this->approvedGap($employee, $firstCompetencyId);
        $secondGapId = $this->approvedGap($employee, $secondCompetencyId);
        DB::table('learning_method_types')->insert([
            'key' => 'experiential-learning',
            'label' => 'Experiential Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $toolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'experiential',
            'title' => 'Project Assignment',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $draftItems = [
            $this->completePlanPayload($firstGapId, $toolId),
            $this->completePlanPayload($secondGapId, $toolId),
        ];

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), ['items' => $draftItems])
            ->assertSessionHasNoErrors();

        $this->actingAs($employee)
            ->post(route('employee.idp.submit-item'), [
                'competencyGapId' => $firstGapId,
                'item' => $draftItems[0],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_items', [
            'competency_gap_id' => $firstGapId,
            'status' => 'review_step_1',
            'submission_version' => 1,
            'current_review_step' => 1,
        ]);
        $this->assertDatabaseHas('idp_items', [
            'competency_gap_id' => $secondGapId,
            'status' => 'draft',
        ]);
    }

    public function test_submitting_idp_item_notifies_first_idp_reviewer(): void
    {
        Mail::fake();

        $supervisor = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        DB::table('user_reviewer_steps')->insert([
            'user_id' => $employee->id,
            'reviewer_id' => $supervisor->id,
            'step_order' => 1,
            'chain_type' => 'idp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $competencyId = $this->competencyId('CC-IDP-NOTIFY');
        $gapId = $this->approvedGap($employee, $competencyId);
        DB::table('learning_method_types')->insert([
            'key' => 'experiential-learning',
            'label' => 'Experiential Learning',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $toolId = DB::table('idp_learning_methods')->insertGetId([
            'focus_type' => 'experiential',
            'title' => 'Project Assignment',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.submit-item'), [
                'item' => $this->completePlanPayload($gapId, $toolId),
            ])
            ->assertSessionHasNoErrors();

        Mail::assertSent(IdpSubmittedMail::class, function (IdpSubmittedMail $mail) use ($employee): bool {
            return $mail->hasTo(self::DEV_NOTIFICATION_RECIPIENT)
                && $mail->employee->is($employee)
                && $mail->competencyName === 'Test Competency';
        });
    }

    public function test_auto_save_does_not_overwrite_submitted_competency_plan(): void
    {
        $supervisor = User::factory()->create([
            'role_id' => $this->roleId('supervisor'),
        ]);
        $employee = User::factory()->create([
            'role_id' => $this->roleId('employee'),
        ]);
        $competencyId = $this->competencyId('CC-LOCKED');
        $gapId = $this->approvedGap($employee, $competencyId);
        $idpId = DB::table('idps')->insertGetId([
            'user_id' => $employee->id,
            'year' => 2568,
            'status' => 'partially_submitted',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('idp_items')->insert([
            'idp_id' => $idpId,
            'competency_gap_id' => $gapId,
            'goal' => 'เป้าหมายที่ส่งแล้ว',
            'success_criteria' => 'เกณฑ์เดิม',
            'status' => 'review_step_1',
            'submission_version' => 1,
            'current_review_step' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($employee)
            ->post(route('employee.idp.draft'), [
                'items' => [[
                    'competencyGapId' => $gapId,
                    'goal' => 'พยายามเขียนทับ',
                    'successCriteria' => 'พยายามเขียนทับ',
                    'activities' => [],
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('idp_items', [
            'competency_gap_id' => $gapId,
            'goal' => 'เป้าหมายที่ส่งแล้ว',
            'status' => 'review_step_1',
        ]);
    }

    public function test_supervisor_type_needs_no_person_selection_in_all_activity_forms(): void
    {
        $employee = User::factory()->create(['role_id' => $this->roleId('employee')]);
        $gapId = $this->approvedGap($employee, $this->competencyId('CC-SUP-TYPE'));
        DB::table('learning_method_types')->insert([
            'key' => 'social-learning', 'label' => 'Social Learning',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([
            'form_4_ojt' => 'trainerType',
            'form_5_coaching' => 'coachType',
            'form_6_mentoring' => 'mentorType',
            'form_7_group_activity' => 'facilitatorType',
            'form_8_feedback' => 'feedbackProviderType',
        ] as $formCode => $typeField) {
            $toolId = DB::table('idp_learning_methods')->insertGetId([
                'focus_type' => 'social', 'title' => $formCode, 'form_code' => $formCode,
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $payload = $this->completePlanPayload($gapId, $toolId);
            $payload['activities'][0]['methodKey'] = 'social-learning';
            $payload['activities'][0]['formCode'] = $formCode;
            $payload['activities'][0]['formDetails'] = [
                '_saved' => true, 'detail' => [$typeField => 'ผู้บังคับบัญชา'], 'planRows' => [],
            ];
            // Validation must proceed to the activity rows, without requiring a supervisor ID.
            $this->actingAs($employee)->postJson(route('employee.idp.submit-item'), ['item' => $payload])
                ->assertUnprocessable()
                ->assertExactJsonStructure(['message', 'errors' => ['items.0.activities.0.formDetails.planRows']]);
        }
    }

    private function approvedGap(User $employee, int $competencyId): int
    {
        $assessment = $this->assessment($employee, $competencyId);

        return (int) DB::table('competency_gaps')->insertGetId([
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
    }

    private function completePlanPayload(int $gapId, int $toolId): array
    {
        return [
            'competencyGapId' => $gapId,
            'goal' => 'พัฒนาสมรรถนะ',
            'successCriteria' => 'ผ่านตามเกณฑ์',
            'activities' => [[
                'methodKey' => 'experiential-learning',
                'developmentToolId' => $toolId,
                'learningCatalogId' => null,
                'activityName' => 'Project Assignment',
                'activityDescription' => 'ฝึกทำงานจริง',
                'documentReferenceNumber' => '',
                'weightPercent' => 100,
                'startDate' => '2026-06-20',
                'endDate' => '2026-07-20',
            ]],
        ];
    }

    private function assessment(User $user, int $competencyId): Assessment
    {
        $values = [
            'user_id' => $user->id,
            'competency_id' => $competencyId,
            'score' => 2,
            'note' => 'ควรพัฒนาต่อ',
            'status' => 'approved',
            'last_draft_saved_at' => now(),
        ];

        if (Schema::hasColumn('assessments', 'assessment_round_id')) {
            $values['assessment_round_id'] = $this->assessmentRoundId();
        }

        return Assessment::create($values);
    }

    private function competencyId(string $code = 'CC-IDP'): int
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

    private function roleId(string $key): int
    {
        return (int) DB::table('roles')->where('key', $key)->value('id');
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
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
