<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationRecipientResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationRecipientResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_default_to_the_development_recipient(): void
    {
        config([
            'mail.send_to_real_users' => false,
            'mail.dev_to' => ['krittheekachon.s@kkumail.com'],
        ]);

        $user = User::factory()->create(['email' => 'real-user@example.test']);

        $this->assertSame(
            'krittheekachon.s@kkumail.com',
            app(NotificationRecipientResolver::class)->recipientsFor($user),
        );
    }

    public function test_notifications_can_send_to_each_users_real_email(): void
    {
        config(['mail.send_to_real_users' => true]);

        $user = User::factory()->create(['email' => 'real-user@example.test']);

        $this->assertTrue(app(NotificationRecipientResolver::class)->recipientsFor($user)->is($user));
    }
}
