<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Edit')
            ->where('profileUser.db_id', $user->id)
            ->missing('status')
        );
    }

    public function test_employee_cannot_update_their_own_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'ชื่อเดิม',
            'email' => 'original@example.com',
            'workline' => 'สายวิชาการ',
            'department' => 'ภาควิชาคอมพิวเตอร์',
            'position' => 'อาจารย์',
            'level' => 'ระดับ 1',
            'sso' => 'personnel-001',
        ]);
        $verifiedAt = $user->email_verified_at;

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'workline' => 'สายสนับสนุน',
                'department' => 'ฝ่ายปลอม',
                'position' => 'ตำแหน่งปลอม',
                'level' => 'ระดับเฉพาะปลอม',
                'sso' => 'changed-by-user',
                'profile_photo' => 'data:image/png;base64,ZmFrZQ==',
            ])
            ->assertForbidden();

        $user->refresh();

        $this->assertSame('ชื่อเดิม', $user->name);
        $this->assertSame('original@example.com', $user->email);
        $this->assertSame($verifiedAt?->toDateTimeString(), $user->email_verified_at?->toDateTimeString());
        $this->assertSame('สายวิชาการ', $user->workline);
        $this->assertSame('ภาควิชาคอมพิวเตอร์', $user->department);
        $this->assertSame('อาจารย์', $user->position);
        $this->assertSame('ระดับ 1', $user->level);
        $this->assertSame('personnel-001', $user->sso);
        $this->assertNull($user->profile_photo);
    }

    public function test_admin_also_uses_user_management_instead_of_self_service_profile_update(): void
    {
        $adminRoleId = DB::table('roles')->where('key', 'admin')->value('id');
        $user = User::factory()->create(['role_id' => $adminRoleId]);
        $originalName = $user->name;

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ])
            ->assertForbidden();

        $this->assertSame($originalName, $user->refresh()->name);
    }

    public function test_user_can_not_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertMethodNotAllowed();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
    }
}
