<?php

namespace Tests\Feature;

use App\Http\Controllers\MockSsoController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MockSsoLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/_test/mock-sso', [MockSsoController::class, 'login'])
            ->middleware('web');
    }

    public function test_it_logs_in_with_the_database_user_id_when_sso_is_not_numeric(): void
    {
        $user = User::factory()->create(['sso' => 'admin']);

        $response = $this->post('/_test/mock-sso', [
            'user_id' => $user->id,
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_it_rejects_a_non_numeric_user_id_without_querying_the_primary_key(): void
    {
        $response = $this->post('/_test/mock-sso', [
            'user_id' => 'admin',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('user_id');
    }
}
