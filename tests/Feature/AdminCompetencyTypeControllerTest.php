<?php

namespace Tests\Feature;

use App\Models\CompetencyType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCompetencyTypeControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_competency_type(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.competency-types.store'), [
                'code' => 'TC',
                'full_name' => 'Technical Competency',
                'description' => 'สมรรถนะด้านเทคนิค',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $type = CompetencyType::query()->where('code', 'TC')->firstOrFail();

        $this->assertDatabaseHas('competency_types', [
            'id' => $type->id,
            'full_name' => 'Technical Competency',
            'description' => 'สมรรถนะด้านเทคนิค',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.competency-types.update', $type), [
                'code' => 'DC',
                'full_name' => 'Digital Competency',
                'description' => 'สมรรถนะด้านดิจิทัล',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('competency_types', [
            'id' => $type->id,
            'code' => 'DC',
            'full_name' => 'Digital Competency',
            'description' => 'สมรรถนะด้านดิจิทัล',
        ]);
        $this->assertDatabaseMissing('competency_types', [
            'code' => 'TC',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.competency-types.destroy', $type))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('competency_types', [
            'id' => $type->id,
        ]);
    }

    public function test_admin_cannot_create_competency_type_with_duplicate_code(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);

        CompetencyType::create([
            'code' => 'CC',
            'full_name' => 'Core Competency',
            'description' => 'สมรรถนะหลัก',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.competency-types.store'), [
                'code' => 'CC',
                'full_name' => 'Duplicate Core Competency',
                'description' => 'ข้อมูลซ้ำ',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['code']);

        $this->assertSame(1, CompetencyType::query()->where('code', 'CC')->count());
        $this->assertDatabaseMissing('competency_types', [
            'full_name' => 'Duplicate Core Competency',
        ]);
    }

    public function test_admin_can_update_competency_type_without_changing_its_code(): void
    {
        $admin = User::factory()->create(['role_id' => 0, 'role_key' => 'admin']);
        $type = CompetencyType::create([
            'code' => 'MC',
            'full_name' => 'Managerial Competency',
            'description' => 'คำอธิบายเดิม',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.competency-types.update', $type), [
                'code' => 'MC',
                'full_name' => 'Management Competency',
                'description' => 'คำอธิบายใหม่',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('competency_types', [
            'id' => $type->id,
            'code' => 'MC',
            'full_name' => 'Management Competency',
            'description' => 'คำอธิบายใหม่',
        ]);
    }
}
