<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminLearningCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_manage_learning_catalog(): void
    {
        $roleId = DB::table('roles')->where('key', 'hr')->value('id')
            ?: DB::table('roles')->insertGetId([
                'key' => 'hr', 'name_th' => 'ทรัพยากรบุคคล', 'name_en' => 'HR',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        $hr = User::factory()->create(['role_id' => $roleId, 'role_key' => 'hr']);

        $this->actingAs($hr)
            ->postJson(route('admin.learning-catalogs.store'), [])
            ->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_learning_catalog(): void
    {
        $adminUser = $this->adminUser();
        $methodId = $this->createLearningMethod('workshop', 'Workshop');
        $competencyId = $this->createCompetency('CC-001', 'Customer First');

        $this->actingAs($adminUser)
            ->post(route('admin.learning-catalogs.store'), [
                'code' => 'KKU-LC-001',
                'name' => 'หลักสูตรทดสอบ',
                'method_key' => 'workshop',
                'delivery_type' => 'e_learning',
                'source_type' => 'internal',
                'provider' => 'หน่วยงานทดสอบ',
                'cost' => '1200',
                'hours' => '6',
                'expected_levels' => [2, 3],
                'competency_ids' => [$competencyId],
                'description' => 'รายละเอียดหลักสูตร',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('learning_catalogs', [
            'code' => 'KKU-LC-001',
            'name' => 'หลักสูตรทดสอบ',
            'method_type_id' => $methodId,
            'delivery_type' => 'e_learning',
            'source_type' => 'internal',
            'provider' => null,
            'cost' => 1200,
            'hours' => 6,
            'description' => 'รายละเอียดหลักสูตร',
            'is_active' => true,
        ]);

        $catalogId = DB::table('learning_catalogs')->value('id');
        $this->assertDatabaseHas('learning_catalog_competency', [
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
        ]);
        $this->assertSame([2, 3], json_decode(DB::table('learning_catalogs')->where('id', $catalogId)->value('expected_levels'), true));

        $this->actingAs($adminUser)
            ->put(route('admin.learning-catalogs.update', $catalogId), [
                'code' => 'KKU-LC-002',
                'name' => 'หลักสูตรแก้ไข',
                'method_key' => 'workshop',
                'delivery_type' => 'in_class',
                'source_type' => 'external',
                'provider' => 'หน่วยงานใหม่',
                'cost' => null,
                'hours' => '3.5',
                'expected_levels' => [],
                'competency_ids' => [$competencyId],
                'description' => 'รายละเอียดใหม่',
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('learning_catalogs', [
            'id' => $catalogId,
            'code' => 'KKU-LC-002',
            'name' => 'หลักสูตรแก้ไข',
            'delivery_type' => 'in_class',
            'source_type' => 'internal',
            'provider' => null,
            'cost' => null,
            'hours' => 3.5,
            'description' => 'รายละเอียดใหม่',
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('learning_catalog_competency', [
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
        ]);

        $this->actingAs($adminUser)
            ->delete(route('admin.learning-catalogs.destroy', $catalogId))
            ->assertRedirect();

        $this->assertDatabaseMissing('learning_catalogs', ['id' => $catalogId]);
    }

    public function test_in_class_catalog_can_be_created_with_empty_expected_levels(): void
    {
        $adminUser = $this->adminUser();
        $this->createLearningMethod('formal', 'Formal Learning');
        $competencyId = $this->createCompetency('CC-INCLASS', 'In-class competency');

        $this->actingAs($adminUser)->post(route('admin.learning-catalogs.store'), [
            'code' => 'TN001',
            'name' => 'หลักสูตรในห้องเรียน',
            'method_key' => 'formal',
            'delivery_type' => 'in_class',
            'source_type' => 'internal',
            'expected_levels' => [],
            'competency_ids' => [$competencyId],
            'is_active' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('learning_catalogs', [
            'code' => 'TN001',
            'delivery_type' => 'in_class',
            'expected_levels' => null,
        ]);
    }

    public function test_catalog_fields_are_required_by_delivery_type(): void
    {
        $adminUser = $this->adminUser();
        $this->createLearningMethod('formal', 'Formal Learning');

        $this->actingAs($adminUser)
            ->post(route('admin.learning-catalogs.store'), [
                'code' => null,
                'name' => 'หลักสูตรในห้องเรียน',
                'method_key' => 'formal',
                'delivery_type' => 'in_class',
                'source_type' => 'internal',
                'provider' => null,
                'expected_levels' => [],
                'competency_ids' => [],
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['code', 'competency_ids']);

        $competencyId = $this->createCompetency('CC-030', 'Digital Literacy');

        $this->actingAs($adminUser)
            ->post(route('admin.learning-catalogs.store'), [
                'code' => null,
                'name' => 'บทเรียนออนไลน์',
                'method_key' => 'formal',
                'delivery_type' => 'e_learning',
                'source_type' => 'internal',
                'provider' => null,
                'expected_levels' => [],
                'competency_ids' => [$competencyId],
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['expected_levels']);
    }

    public function test_admin_dashboard_loads_learning_catalog_and_idp_tool_items(): void
    {
        $adminUser = $this->adminUser();
        $methodId = $this->createLearningMethod('online', 'Online Learning');
        $competencyId = $this->createCompetency('CC-002', 'Expertise');
        DB::table('idp_learning_methods')->insert([
            'focus_type' => 'experiential',
            'title' => 'การมอบหมายงานโครงการ',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'code' => 'ONLINE-001',
            'name' => 'หลักสูตรออนไลน์',
            'method_type_id' => $methodId,
            'delivery_type' => 'e_learning',
            'source_type' => 'external',
            'provider' => 'ผู้จัด',
            'cost' => 0,
            'hours' => 2,
            'expected_levels' => json_encode([1, 2]),
            'description' => 'รายละเอียด',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($adminUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('hrCatalogItems.0.code', 'ONLINE-001')
                ->where('hrCatalogItems.0.name', 'หลักสูตรออนไลน์')
                ->where('hrCatalogItems.0.methodKey', 'online')
                ->where('hrCatalogItems.0.methodLabel', 'Online Learning')
                ->where('hrCatalogItems.0.deliveryType', 'e_learning')
                ->where('hrCatalogItems.0.sourceType', 'external')
                ->where('hrCatalogItems.0.hours', 2)
                ->where('hrCatalogItems.0.expectedLevels', [1, 2])
                ->where('hrCatalogItems.0.competencyIds', [$competencyId])
                ->where('hrCatalogItems.0.isActive', true)
                ->where('idpLearningMethods.0.focusType', 'experiential')
                ->where('idpLearningMethods.0.title', 'การมอบหมายงานโครงการ / งานพิเศษ (Project Assignment)')
            );
    }

    public function test_admin_can_update_idp_delivery_type_codes(): void
    {
        $adminUser = $this->adminUser();

        $this->actingAs($adminUser)
            ->put(route('admin.idp-delivery-type-settings.update'), [
                'delivery_types' => [
                    'e_learning' => '09',
                    'in_class' => '10',
                ],
                'delivery_forms' => [
                    'e_learning' => 'form_10_training',
                    'in_class' => 'form_10_training',
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('learning_catalog_delivery_types', [
            'key' => 'e_learning',
            'code' => '09',
            'name_th' => 'การฝึกอบรมออนไลน์',
            'name_en' => 'e-Learning',
        ]);
        $this->assertDatabaseHas('learning_catalog_delivery_types', [
            'key' => 'in_class',
            'code' => '10',
            'name_th' => 'การฝึกอบรมในห้องเรียน',
            'name_en' => 'In Class Training',
        ]);

        $this->actingAs($adminUser)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->where('idpDeliveryTypeSettings.0.value', 'e_learning')
                ->where('idpDeliveryTypeSettings.0.code', '09')
                ->where('idpDeliveryTypeSettings.1.value', 'in_class')
                ->where('idpDeliveryTypeSettings.1.code', '10')
            );
    }

    public function test_admin_delivery_type_codes_must_be_numeric(): void
    {
        $adminUser = $this->adminUser();

        $this->actingAs($adminUser)
            ->from('/dashboard')
            ->put(route('admin.idp-delivery-type-settings.update'), [
                'delivery_types' => [
                    'e_learning' => 'EL',
                    'in_class' => '10',
                ],
                'delivery_forms' => [
                    'e_learning' => 'form_10_training',
                    'in_class' => 'form_10_training',
                ],
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors(['delivery_types.e_learning']);
    }

    public function test_admin_gets_validation_errors_when_learning_catalog_code_or_name_is_duplicated(): void
    {
        $adminUser = $this->adminUser();
        $this->createLearningMethod('formal', 'Formal Learning');

        DB::table('learning_catalogs')->insert([
            'code' => 'DUP-001',
            'name' => 'หลักสูตรซ้ำ',
            'delivery_type' => 'e_learning',
            'source_type' => 'internal',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($adminUser)
            ->from('/dashboard')
            ->post(route('admin.learning-catalogs.store'), [
                'code' => 'DUP-001',
                'name' => 'หลักสูตรซ้ำ',
                'method_key' => 'formal',
                'delivery_type' => 'e_learning',
                'source_type' => 'internal',
                'provider' => null,
                'cost' => null,
                'hours' => null,
                'expected_levels' => [],
                'competency_ids' => [],
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHasErrors([
                'code' => 'รหัสหลักสูตรนี้ถูกใช้งานแล้ว',
                'name' => 'ชื่อหลักสูตรนี้ถูกใช้งานแล้ว',
            ]);
    }

    public function test_admin_cannot_store_learning_catalog_with_invalid_delivery_or_inactive_method(): void
    {
        $adminUser = $this->adminUser();
        DB::table('learning_method_types')->insert([
            'key' => 'inactive-method',
            'label' => 'Inactive Method',
            'description' => 'Inactive Method',
            'is_active' => false,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($adminUser)
            ->post(route('admin.learning-catalogs.store'), [
                'code' => 'BAD-001',
                'name' => 'หลักสูตรข้อมูลผิด',
                'method_key' => 'inactive-method',
                'delivery_type' => 'field_trip',
                'source_type' => 'internal',
                'provider' => null,
                'cost' => null,
                'hours' => null,
                'expected_levels' => [],
                'competency_ids' => [],
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['method_key', 'delivery_type']);

        $this->assertDatabaseMissing('learning_catalogs', [
            'code' => 'BAD-001',
        ]);
    }

    public function test_admin_cannot_map_learning_catalog_to_more_than_one_competency(): void
    {
        $adminUser = $this->adminUser();
        $this->createLearningMethod('formal', 'Formal Learning');
        $firstCompetencyId = $this->createCompetency('CC-010', 'Customer First');
        $secondCompetencyId = $this->createCompetency('CC-011', 'Expertise');

        $this->actingAs($adminUser)
            ->post(route('admin.learning-catalogs.store'), [
                'code' => 'MAP-001',
                'name' => 'หลักสูตรผูกหลายสมรรถนะ',
                'method_key' => 'formal',
                'delivery_type' => 'e_learning',
                'source_type' => 'internal',
                'provider' => null,
                'cost' => 0,
                'hours' => 1,
                'expected_levels' => [],
                'competency_ids' => [$firstCompetencyId, $secondCompetencyId],
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['competency_ids']);

        $this->assertDatabaseMissing('learning_catalogs', [
            'code' => 'MAP-001',
        ]);
        $this->assertDatabaseMissing('learning_catalog_competency', [
            'competency_id' => $firstCompetencyId,
        ]);
    }

    public function test_e_learning_expected_levels_are_required_and_normalized(): void
    {
        $adminUser = $this->adminUser();
        $this->createLearningMethod('formal', 'Formal Learning');
        $competencyId = $this->createCompetency('CC-012', 'Systems Thinking');

        $this->actingAs($adminUser)
            ->post(route('admin.learning-catalogs.store'), [
                'code' => 'LV-001',
                'name' => 'หลักสูตรไม่ระบุระดับ',
                'method_key' => 'formal',
                'delivery_type' => 'e_learning',
                'source_type' => 'internal',
                'provider' => 'ศูนย์นวัตกรรมการเรียนการสอน',
                'cost' => 0,
                'hours' => 1,
                'expected_levels' => [],
                'competency_ids' => [$competencyId],
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['expected_levels']);

        $this->assertDatabaseMissing('learning_catalogs', ['code' => 'LV-001']);

        $this->actingAs($adminUser)
            ->post(route('admin.learning-catalogs.store'), [
                'code' => 'LV-001',
                'name' => 'หลักสูตรไม่ระบุระดับ',
                'method_key' => 'formal',
                'delivery_type' => 'e_learning',
                'source_type' => 'internal',
                'provider' => 'ศูนย์นวัตกรรมการเรียนการสอน',
                'cost' => 0,
                'hours' => 1,
                'expected_levels' => [4, 2, 2],
                'competency_ids' => [$competencyId],
                'description' => null,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $catalogId = DB::table('learning_catalogs')->where('code', 'LV-001')->value('id');

        $this->assertSame([2, 4], json_decode(DB::table('learning_catalogs')->where('id', $catalogId)->value('expected_levels'), true));
    }

    public function test_deleting_learning_catalog_removes_competency_mapping(): void
    {
        $adminUser = $this->adminUser();
        $competencyId = $this->createCompetency('CC-020', 'Result Driven');
        $catalogId = DB::table('learning_catalogs')->insertGetId([
            'code' => 'DEL-001',
            'name' => 'หลักสูตรที่จะลบ',
            'delivery_type' => 'in_class',
            'source_type' => 'internal',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('learning_catalog_competency')->insert([
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($adminUser)
            ->delete(route('admin.learning-catalogs.destroy', $catalogId))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('learning_catalogs', [
            'id' => $catalogId,
        ]);
        $this->assertDatabaseMissing('learning_catalog_competency', [
            'learning_catalog_id' => $catalogId,
            'competency_id' => $competencyId,
        ]);
    }

    private function createLearningMethod(string $key, string $label): int
    {
        return DB::table('learning_method_types')->insertGetId([
            'key' => $key,
            'label' => $label,
            'description' => $label,
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function adminUser(): User
    {
        $roleId = DB::table('roles')->where('key', 'admin')->value('id')
            ?: DB::table('roles')->insertGetId([
                'key' => 'admin',
                'name_th' => 'ผู้ดูแลระบบ',
                'name_en' => 'Admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return User::factory()->create(['role_id' => $roleId, 'role_key' => 'admin']);
    }

    private function createCompetency(string $code, string $name): int
    {
        $typeId = DB::table('competency_types')->insertGetId([
            'code' => $code.'-TYPE',
            'full_name' => 'Core Competency',
            'description' => 'Core Competency',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('competencies')->insertGetId([
            'competency_type_id' => $typeId,
            'code' => $code,
            'name' => $name,
            'detail' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
