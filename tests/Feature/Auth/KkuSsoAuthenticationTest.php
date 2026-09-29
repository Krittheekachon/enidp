<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KkuSsoAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.kku_sso', [
            'enabled' => true,
            'app_id' => 'test-app-id',
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'login_url' => 'https://ssonext.kku.ac.th/login',
            'token_url' => 'https://ssonext-api.kku.ac.th/auth.token',
            'redirect_url' => 'https://idp.enit.kku.ac.th/auth/kku/callback',
            'logout_url' => 'https://ssonext.kku.ac.th/logout',
            'logout_redirect_url' => 'https://idp.enit.kku.ac.th',
        ]);
    }

    public function test_it_redirects_to_the_kku_login_page(): void
    {
        $response = $this->get('/auth/kku');

        $response->assertRedirect('https://ssonext.kku.ac.th/login?app=test-app-id');
        $response->assertSessionHas('kku_sso_attempted_at');
    }

    public function test_existing_user_can_log_in_with_kku_employee_id(): void
    {
        $user = User::factory()->create([
            'sso' => '000123',
            'email' => 'person@kku.ac.th',
        ]);
        Http::fake([
            'https://ssonext-api.kku.ac.th/auth.token' => Http::response([
                'ok' => true,
                'accessToken' => 'not-stored',
                'email' => 'person@kku.ac.th',
                'employeeId' => '000123',
            ]),
        ]);

        $response = $this
            ->withSession(['kku_sso_attempted_at' => now()->timestamp])
            ->get('/auth/kku/callback?code=one-time-code');

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('kku_sso_authenticated', true);
        Http::assertSent(fn ($request) => $request->url() === 'https://ssonext-api.kku.ac.th/auth.token'
            && $request['code'] === 'one-time-code'
            && $request['redirectUrl'] === 'https://idp.enit.kku.ac.th/auth/kku/callback'
            && $request['clientId'] === 'test-client-id'
            && $request['clientSecret'] === 'test-client-secret');
    }

    public function test_email_is_used_when_employee_id_does_not_match(): void
    {
        $user = User::factory()->create([
            'sso' => 'legacy-id',
            'email' => 'person@kku.ac.th',
        ]);
        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'email' => 'PERSON@KKU.AC.TH',
                'employeeId' => 'different-id',
            ]),
        ]);

        $response = $this
            ->withSession(['kku_sso_attempted_at' => now()->timestamp])
            ->get('/auth/kku/callback?code=one-time-code');

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_unknown_kku_user_is_not_created_or_authenticated(): void
    {
        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'email' => 'unknown@kku.ac.th',
                'employeeId' => '999999',
            ]),
        ]);

        $response = $this
            ->withSession(['kku_sso_attempted_at' => now()->timestamp])
            ->get('/auth/kku/callback?code=one-time-code');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $response->assertRedirect('/');
        $response->assertSessionHas('sso_error');
    }

    public function test_inactive_user_cannot_log_in_with_kku_sso(): void
    {
        User::factory()->create([
            'sso' => '000123',
            'is_active' => false,
        ]);
        Http::fake([
            '*' => Http::response([
                'ok' => true,
                'email' => 'person@kku.ac.th',
                'employeeId' => '000123',
            ]),
        ]);

        $response = $this
            ->withSession(['kku_sso_attempted_at' => now()->timestamp])
            ->get('/auth/kku/callback?code=one-time-code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('sso_error');
    }

    public function test_kku_sso_login_uses_kku_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['kku_sso_authenticated' => true])
            ->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('https://ssonext.kku.ac.th/logout?app=test-app-id');
    }
}
