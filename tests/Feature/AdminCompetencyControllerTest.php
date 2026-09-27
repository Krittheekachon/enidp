<?php

namespace Tests\Feature;

use App\Models\Competency;
use App\Models\CompetencyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminCompetencyControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_competency_dictionary_item(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), [
                'competency_type_id' => $type->id,
                'code' => 'CC-001',
                'name' => 'การบริการที่ดี',
                'detail' => 'ให้บริการด้วยความเข้าใจ',
                'levels' => [
                    [
                        'level' => 1,
                        'description' => '',
                        'indicators' => [
                            ['description' => 'รับฟังผู้รับบริการ', 'weight' => 0.25],
                            ['description' => 'ตอบกลับอย่างสุภาพ', 'weight' => 0.25],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $competency = Competency::query()->where('code', 'CC-001')->firstOrFail();

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'competency_type_id' => $type->id,
            'name' => 'การบริการที่ดี',
        ]);
        $this->assertDatabaseHas('competency_levels', [
            'competency_id' => $competency->id,
            'level' => 1,
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'รับฟังผู้รับบริการ',
            'weight' => 0.25,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.competencies.update', $competency), [
                'competency_type_id' => $type->id,
                'code' => 'CC-002',
                'name' => 'การมุ่งผลสัมฤทธิ์',
                'detail' => 'ทำงานให้เกิดผลลัพธ์',
                'levels' => [
                    [
                        'level' => 2,
                        'description' => '',
                        'indicators' => [
                            ['description' => 'ติดตามผลการทำงาน', 'weight' => 0.50],
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'code' => 'CC-002',
            'name' => 'การมุ่งผลสัมฤทธิ์',
        ]);
        $this->assertDatabaseMissing('comp_level_indicators', [
            'description' => 'รับฟังผู้รับบริการ',
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'ติดตามผลการทำงาน',
            'weight' => 0.50,
        ]);

        $updatedLevel = $competency->levels()->where('level', 2)->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('admin.competencies.destroy', $competency))
            ->assertRedirect();

        $this->assertDatabaseMissing('competencies', ['id' => $competency->id]);
        $this->assertDatabaseMissing('competency_levels', ['id' => $updatedLevel->id]);
        $this->assertDatabaseMissing('comp_level_indicators', [
            'competency_level_id' => $updatedLevel->id,
        ]);
    }

    public function test_admin_can_import_competencies_from_csv(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $csv = implode("\n", [
            'type,code,name,description,level,level_description,indicator,weight',
            'CC,CC-001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,1,ระดับพื้นฐาน,รับฟังผู้รับบริการ,0.25',
            'CC,CC-001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,1,ระดับพื้นฐาน,รับฟังผู้รับบริการ,0.25',
            'CC,CC-001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,2,ระดับทำได้ดี,แก้ปัญหาเฉพาะหน้า,0.50',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect();

        $competency = Competency::query()->where('code', 'CC-001')->firstOrFail();

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'competency_type_id' => $type->id,
            'name' => 'การบริการที่ดี',
            'detail' => 'ให้บริการด้วยความเข้าใจ',
        ]);
        $this->assertDatabaseHas('competency_levels', [
            'competency_id' => $competency->id,
            'level' => 1,
            'description' => 'ระดับพื้นฐาน',
        ]);
        $this->assertDatabaseHas('competency_levels', [
            'competency_id' => $competency->id,
            'level' => 2,
            'description' => 'ระดับทำได้ดี',
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'รับฟังผู้รับบริการ',
            'weight' => 0.25,
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'แก้ปัญหาเฉพาะหน้า',
            'weight' => 0.50,
        ]);
    }

    public function test_admin_can_import_competency_with_long_name(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        CompetencyType::create([
            'code' => 'FC',
            'full_name' => 'Functional Competency',
            'description' => 'สมรรถนะตามสายงาน',
        ]);

        $longName = str_repeat('การออกแบบและวางแผนการจัดการการเรียนรู้', 12);
        $csv = implode("\n", [
            'type,code,name,description,level,indicator_order,indicator,weight',
            'FC,FC1-003,"'.$longName.'",รายละเอียดสมรรถนะ,1,1,พฤติกรรมบ่งชี้ระดับหนึ่ง,0.25',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('competencies', [
            'code' => 'FC1-003',
            'name' => $longName,
        ]);
    }

    public function test_admin_cannot_create_competency_with_duplicate_name(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);
        Competency::create([
            'competency_type_id' => $type->id,
            'code' => 'CC-001',
            'name' => 'การบริการที่ดี',
            'detail' => 'ให้บริการด้วยความเข้าใจ',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), [
                'competency_type_id' => $type->id,
                'code' => 'CC-002',
                'name' => 'การบริการที่ดี',
                'detail' => 'ชื่อซ้ำแต่รหัสไม่ซ้ำ',
                'levels' => [
                    [
                        'level' => 1,
                        'description' => '',
                        'indicators' => [
                            ['description' => 'รับฟังผู้รับบริการ', 'weight' => 0.25],
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['name']);

        $this->assertDatabaseMissing('competencies', [
            'code' => 'CC-002',
        ]);
    }

    public function test_admin_cannot_create_competency_with_duplicate_code(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);
        Competency::create([
            'competency_type_id' => $type->id,
            'code' => 'CC-001',
            'name' => 'การบริการที่ดี',
            'detail' => 'ให้บริการด้วยความเข้าใจ',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.store'), [
                'competency_type_id' => $type->id,
                'code' => 'CC-001',
                'name' => 'การมุ่งผลสัมฤทธิ์',
                'detail' => 'รหัสซ้ำแต่ชื่อไม่ซ้ำ',
                'levels' => [
                    [
                        'level' => 1,
                        'description' => '',
                        'indicators' => [
                            ['description' => 'ติดตามผลการทำงาน', 'weight' => 0.25],
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['code']);

        $this->assertSame(1, Competency::query()->where('code', 'CC-001')->count());
        $this->assertDatabaseMissing('competencies', [
            'name' => 'การมุ่งผลสัมฤทธิ์',
        ]);
    }

    public function test_admin_can_import_competencies_from_template_csv_with_thai_headers(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $csv = implode("\n", [
            "\xEF\xBB\xBF📖  Import Template: พจนานุกรมสมรรถนะ (Competency Dictionary),,,,,,,",
            '1 สมรรถนะ = 20 แถว (5 ระดับ × 4 ข้อ)  •  * = จำเป็น,,,,,,,',
            ',,,,,,,',
            '📋 ข้อมูลหลัก (merge 20 แถวต่อสมรรถนะ),,,,🎯 พฤติกรรมบ่งชี้ (แยกตามระดับ → ข้อย่อย),,,',
            'ประเภท *,รหัส *,ชื่อสมรรถนะ *,คำอธิบาย *,ระดับ *,ข้อที่ *,น้ำหนัก *,พฤติกรรมบ่งชี้ *',
            "\"CC / MC\nFC1 / FC2\",\"ตัวเลข\nเช่น 001\",ชื่อเต็ม (ห้ามซ้ำ),นิยาม/ความหมายของสมรรถนะ,\"merge 4 แถว\n(1 ระดับ = 4 ข้อ)\",1 / 2 / 3 / 4,\"0.00–1.00\nรวม 4 ข้อ = 1.00\",ประโยคพฤติกรรมที่สังเกตได้จริง",
            "CC,001,การมุ่งเน้นผู้เรียนและผู้รับบริการ (Customer First),ช่วยเหลือและดูแลผู้เรียน,\"ระดับที่ 1\nBasic\",1,0.25,สามารถระบุความต้องการของผู้เรียนและผู้รับบริการได้",
            "CC,001,การมุ่งเน้นผู้เรียนและผู้รับบริการ (Customer First),ช่วยเหลือและดูแลผู้เรียน,\"ระดับที่ 1\nBasic\",2,0.25,ตอบสนองต่อคำถามหรือปัญหาของผู้เรียนด้วยความสุภาพ",
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('KKU_Competency_Filled.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $competency = Competency::query()->where('code', 'CC-001')->firstOrFail();

        $this->assertDatabaseHas('competencies', [
            'id' => $competency->id,
            'competency_type_id' => $type->id,
            'name' => 'การมุ่งเน้นผู้เรียนและผู้รับบริการ (Customer First)',
            'detail' => 'ช่วยเหลือและดูแลผู้เรียน',
        ]);
        $this->assertDatabaseHas('competency_levels', [
            'competency_id' => $competency->id,
            'level' => 1,
            'description' => "ระดับที่ 1\nBasic",
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'สามารถระบุความต้องการของผู้เรียนและผู้รับบริการได้',
            'weight' => 0.25,
        ]);
    }

    public function test_admin_can_import_template_csv_with_merged_cell_blanks(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $csv = implode("\n", [
            'ประเภท *,รหัส *,ชื่อสมรรถนะ *,คำอธิบาย *,ระดับ *,ข้อที่ *,น้ำหนัก *,พฤติกรรมบ่งชี้ *',
            "CC,001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,\"ระดับที่ 1\nBasic\",1,0.25,รับฟังผู้รับบริการ",
            ',,,,,2,0.25,ตอบกลับอย่างสุภาพ',
            ',,,,,3,0.25,ติดตามความต้องการ',
            ",,,,\"ระดับที่ 2\nDoing\",1,0.50,แก้ปัญหาเฉพาะหน้า",
            ',,,,,2,0.50,ประสานงานต่อเนื่อง',
            'CC,002,การมุ่งผลสัมฤทธิ์,ทำงานให้เกิดผลลัพธ์,ระดับที่ 1,1,0.25,ติดตามผลงาน',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $firstCompetency = Competency::query()->where('code', 'CC-001')->firstOrFail();
        $secondCompetency = Competency::query()->where('code', 'CC-002')->firstOrFail();

        $this->assertDatabaseHas('competencies', [
            'id' => $firstCompetency->id,
            'competency_type_id' => $type->id,
            'name' => 'การบริการที่ดี',
        ]);
        $this->assertDatabaseHas('competency_levels', [
            'competency_id' => $firstCompetency->id,
            'level' => 1,
        ]);
        $this->assertDatabaseHas('competency_levels', [
            'competency_id' => $firstCompetency->id,
            'level' => 2,
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'รับฟังผู้รับบริการ',
            'weight' => 0.25,
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'ตอบกลับอย่างสุภาพ',
            'weight' => 0.25,
        ]);
        $this->assertDatabaseHas('comp_level_indicators', [
            'description' => 'แก้ปัญหาเฉพาะหน้า',
            'weight' => 0.50,
        ]);
        $this->assertDatabaseHas('competencies', [
            'id' => $secondCompetency->id,
            'name' => 'การมุ่งผลสัมฤทธิ์',
        ]);
    }

    public function test_admin_cannot_import_when_template_indicator_slots_are_incomplete(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $csv = implode("\n", [
            'ประเภท *,รหัส *,ชื่อสมรรถนะ *,คำอธิบาย *,ระดับ *,ข้อที่ *,น้ำหนัก *,พฤติกรรมบ่งชี้ *',
            "CC,001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,\"ระดับที่ 1\nBasic\",1,0.25,รับฟังผู้รับบริการ",
            ',,,,,2,0.25,ตอบกลับอย่างสุภาพ',
            ',,,,,3,0.25,',
            ',,,,,4,0.25,ติดตามความต้องการ',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'file' => 'คอลัมน์ "พฤติกรรมบ่งชี้" ห้ามว่าง (รหัส CC-001, สมรรถนะ "การบริการที่ดี", ระดับ 1, ข้อที่ 3)',
            ]);

        $this->assertDatabaseMissing('competencies', [
            'code' => 'CC-001',
        ]);
    }

    public function test_admin_cannot_import_when_csv_skips_a_level(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $csv = implode("\n", [
            'ประเภท *,รหัส *,ชื่อสมรรถนะ *,คำอธิบาย *,ระดับ *,ข้อที่ *,น้ำหนัก *,พฤติกรรมบ่งชี้ *',
            "CC,001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,\"ระดับที่ 1\nBasic\",1,0.25,รับฟังผู้รับบริการ",
            ",,,,\"ระดับที่ 3\nDeveloping\",1,0.25,แก้ปัญหาซับซ้อน",
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'file' => 'รหัส CC-001, สมรรถนะ "การบริการที่ดี" ขาดระดับ 2 กรุณากรอกระดับให้ต่อเนื่องก่อน import',
            ]);

        $this->assertDatabaseMissing('competencies', [
            'code' => 'CC-001',
        ]);
    }

    public function test_admin_cannot_import_when_csv_skips_an_indicator_order(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $csv = implode("\n", [
            'ประเภท *,รหัส *,ชื่อสมรรถนะ *,คำอธิบาย *,ระดับ *,ข้อที่ *,น้ำหนัก *,พฤติกรรมบ่งชี้ *',
            "CC,001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,\"ระดับที่ 1\nBasic\",1,0.25,รับฟังผู้รับบริการ",
            ',,,,,2,0.25,ตอบกลับอย่างสุภาพ',
            ',,,,,4,0.25,ติดตามความต้องการ',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'file' => 'รหัส CC-001, สมรรถนะ "การบริการที่ดี", ระดับ 1 ขาดข้อที่ 3 กรุณากรอกข้อที่ให้ต่อเนื่องก่อน import',
            ]);

        $this->assertDatabaseMissing('competencies', [
            'code' => 'CC-001',
        ]);
    }

    public function test_admin_cannot_import_when_competency_code_already_exists(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);
        Competency::create([
            'competency_type_id' => $type->id,
            'code' => 'CC-001',
            'name' => 'ชื่อเดิมในระบบ',
            'detail' => 'รายละเอียดเดิม',
        ]);

        $csv = implode("\n", [
            'type,code,name,description,level,level_description,indicator,weight',
            'CC,CC-001,ชื่อใหม่จากไฟล์,รายละเอียดใหม่,1,ระดับพื้นฐาน,รับฟังผู้รับบริการ,0.25',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'file' => 'รหัสสมรรถนะ CC-001 มีอยู่แล้วในระบบ (ชื่อเดิม "ชื่อเดิมในระบบ", ชื่อในไฟล์ "ชื่อใหม่จากไฟล์") กรุณาตรวจสอบข้อมูลสมรรถนะก่อนนำเข้า',
            ]);

        $this->assertDatabaseHas('competencies', [
            'code' => 'CC-001',
            'name' => 'ชื่อเดิมในระบบ',
            'detail' => 'รายละเอียดเดิม',
        ]);
    }

    public function test_admin_can_import_new_competencies_while_skipping_unchanged_existing_competencies(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);
        $existing = Competency::create([
            'competency_type_id' => $type->id,
            'code' => 'CC-001',
            'name' => 'การบริการที่ดี',
            'detail' => 'ให้บริการด้วยความเข้าใจ',
        ]);
        $level = $existing->levels()->create([
            'level' => 1,
            'description' => 'ระดับพื้นฐาน',
        ]);
        $level->indicators()->create([
            'description' => 'รับฟังผู้รับบริการ',
            'weight' => 0.25,
        ]);

        $csv = implode("\n", [
            'type,code,name,description,level,level_description,indicator,weight',
            'CC,CC-001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,1,ระดับพื้นฐาน,รับฟังผู้รับบริการ,0.25',
            'CC,CC-002,การมุ่งผลสัมฤทธิ์,ทำงานให้เกิดผลลัพธ์,1,ระดับพื้นฐาน,ติดตามผลงาน,0.25',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Competency::query()->where('code', 'CC-001')->count());
        $this->assertDatabaseHas('competencies', [
            'code' => 'CC-002',
            'name' => 'การมุ่งผลสัมฤทธิ์',
        ]);
    }

    public function test_admin_cannot_import_when_existing_competency_content_is_different(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);
        $existing = Competency::create([
            'competency_type_id' => $type->id,
            'code' => 'CC-001',
            'name' => 'การบริการที่ดี',
            'detail' => 'ให้บริการด้วยความเข้าใจ',
        ]);
        $level = $existing->levels()->create([
            'level' => 1,
            'description' => 'ระดับพื้นฐาน',
        ]);
        $level->indicators()->create([
            'description' => 'รับฟังผู้รับบริการ',
            'weight' => 0.25,
        ]);

        $csv = implode("\n", [
            'type,code,name,description,level,level_description,indicator,weight',
            'CC,CC-001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,1,ระดับพื้นฐาน,เปลี่ยนพฤติกรรมบ่งชี้,0.25',
            'CC,CC-002,การมุ่งผลสัมฤทธิ์,ทำงานให้เกิดผลลัพธ์,1,ระดับพื้นฐาน,ติดตามผลงาน,0.25',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'file' => 'พบว่าสมรรถนะ "การบริการที่ดี" (รหัส CC-001) มีข้อมูลในไฟล์ที่แตกต่างจากข้อมูลในระบบที่ ระดับ 1 ข้อที่ 1 ไม่สามารถ import ได้ โปรดเช็คความถูกต้อง',
            ]);

        $this->assertDatabaseMissing('competencies', [
            'code' => 'CC-002',
        ]);
        $this->assertDatabaseMissing('comp_level_indicators', [
            'description' => 'เปลี่ยนพฤติกรรมบ่งชี้',
        ]);
    }

    public function test_admin_cannot_import_when_competency_name_already_exists(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);
        Competency::create([
            'competency_type_id' => $type->id,
            'code' => 'CC-001',
            'name' => 'ชื่อเดิมในระบบ',
            'detail' => 'รายละเอียดเดิม',
        ]);

        $csv = implode("\n", [
            'type,code,name,description,level,level_description,indicator,weight',
            'CC,CC-002,ชื่อเดิมในระบบ,รายละเอียดใหม่,1,ระดับพื้นฐาน,รับฟังผู้รับบริการ,0.25',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'file' => 'ชื่อสมรรถนะ "ชื่อเดิมในระบบ" มีอยู่แล้วในระบบ (รหัสเดิม CC-001, รหัสในไฟล์ CC-002) กรุณาตรวจสอบข้อมูลสมรรถนะก่อนนำเข้า',
            ]);

        $this->assertDatabaseMissing('competencies', [
            'code' => 'CC-002',
        ]);
    }

    public function test_admin_gets_clear_csv_import_errors_with_row_context(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $csv = implode("\n", [
            'ประเภท *,รหัส *,ชื่อสมรรถนะ *,คำอธิบาย *,ระดับ *,ข้อที่ *,น้ำหนัก *,พฤติกรรมบ่งชี้ *',
            "CC,001,การบริการที่ดี,ให้บริการด้วยความเข้าใจ,\"ระดับที่ 1\nBasic\",,,",
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competencies.import'), [
                'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors([
                'file' => 'คอลัมน์ "พฤติกรรมบ่งชี้" ห้ามว่าง (รหัส CC-001, สมรรถนะ "การบริการที่ดี", ระดับ 1)',
            ]);
    }
}
