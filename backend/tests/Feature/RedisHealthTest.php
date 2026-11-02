<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

/**
 * Covers the TEMPORARY Redis connectivity diagnostic endpoint.
 *
 * The test environment has no Redis server, so the endpoint is expected to
 * report `connected: false` with the safe failure payload. That is exactly what
 * these tests assert: the authorization boundary and the response shape. The
 * live-connection path is exercised by the deployed environment (and by
 * RedisConnectivityTest where a Redis server exists).
 */
class RedisHealthTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_access_the_redis_health_endpoint(): void
    {
        $this->actingAsRole('Admin');

        $response = $this->getJson('/api/v1/system/redis-health');

        $response->assertOk()->assertJsonStructure(['status', 'connected']);

        // No Redis in the test env: the endpoint must honestly report failure,
        // never a hardcoded success.
        $response->assertJson(['status' => 'error', 'connected' => false]);
    }

    public function test_guest_is_rejected_without_authentication(): void
    {
        $this->getJson('/api/v1/system/redis-health')->assertUnauthorized();
    }

    public function test_non_admin_role_is_forbidden(): void
    {
        $this->actingAsRole('Receptionist');

        $this->getJson('/api/v1/system/redis-health')->assertForbidden();
    }

    public function test_response_never_leaks_connection_internals(): void
    {
        $this->actingAsRole('Admin');

        $body = $this->getJson('/api/v1/system/redis-health')->assertOk()->json();

        $serialized = json_encode($body);

        foreach (['host', 'port', 'username', 'password', 'redis://', 'rediss://', 'upstash', 'stack', 'trace', 'exception'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $serialized, "Response leaked '{$needle}'.");
        }

        // Only the three documented keys may appear.
        $this->assertSame(['status', 'connected', 'message'], array_keys($body));
    }
}
