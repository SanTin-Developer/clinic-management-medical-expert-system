<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class SessionPolicyTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_access_token_expires_after_the_configured_lifetime(): void
    {
        config()->set('sanctum.expiration', 480);

        $user = $this->userWithRole('Doctor');
        $plain = $user->createToken('test', ['*'], now()->addMinutes(480))->plainTextToken;

        $this->assertNotNull($user->tokens()->latest('id')->first()->expires_at);

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        // 8 hours is the configured lifetime; an extra hour invalidates it.
        $this->travel(9)->hours();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
