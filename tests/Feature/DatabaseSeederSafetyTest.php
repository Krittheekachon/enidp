<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_demo_users_in_testing(): void
    {
        app(DatabaseSeeder::class)->run();

        $this->assertDatabaseHas('users', ['email' => 'admin@test.com']);
    }

    public function test_database_seeder_does_not_create_demo_users_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            app(DatabaseSeeder::class)->run();
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $this->assertDatabaseMissing('users', ['email' => 'admin@test.com']);
        $this->assertDatabaseHas('competency_types', ['code' => 'CC']);
    }
}
