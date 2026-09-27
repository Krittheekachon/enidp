<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_authenticate_with_an_admin_assigned_username(): void
    {
        User::factory()->create([
            'username' => 'somchai.k',
            'password' => 'secure-password',
        ]);

        $response = $this->post('/login', [
            'email' => 'SOMCHAI.K',
            'password' => 'secure-password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_login_post_replaces_an_existing_authenticated_session(): void
    {
        $admin = User::factory()->create([
            'username' => 'admin',
            'password' => 'admin-password',
        ]);
        $krit = User::factory()->create([
            'username' => 'krit',
            'password' => 'krit-password',
        ]);

        $response = $this->actingAs($admin)->post('/login', [
            'email' => 'krit',
            'password' => 'krit-password',
        ]);

        $this->assertAuthenticatedAs($krit);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_test_email_prefix_is_not_treated_as_a_username(): void
    {
        User::factory()->create([
            'email' => 'legacy-user@test.com',
            'username' => null,
            'password' => 'secure-password',
        ]);

        $this->post('/login', [
            'email' => 'legacy-user',
            'password' => 'secure-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_inactive_users_can_not_authenticate(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors([
            'email' => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
        ]);
    }

    public function test_existing_session_is_ended_when_account_becomes_inactive(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $response = $this->actingAs($user)->get('/dashboard');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
