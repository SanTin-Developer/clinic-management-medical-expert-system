<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_openapi_document_is_available_locally(): void
    {
        $this->app['env'] = 'local';

        $response = $this->get('/docs/api.json');

        $response->assertOk();

        $json = $response->json();

        $this->assertSame('3.1.0', $json['openapi']);
        $this->assertArrayHasKey('/v1/auth/login', $json['paths']);
        $this->assertArrayHasKey('/v1/auth/otp/verify', $json['paths']);
        $this->assertArrayHasKey('/v1/reports/{report}/pdf', $json['paths']);
    }

    public function test_api_docs_are_forbidden_outside_local_environment_by_default(): void
    {
        $this->get('/docs/api.json')->assertForbidden();
    }

    public function test_admin_can_access_api_docs_outside_local_environment(): void
    {
        $admin = $this->userWithRole('Admin');

        $this->actingAs($admin)->get('/docs/api.json')->assertOk();
    }

    public function test_non_admin_cannot_access_api_docs_outside_local_environment(): void
    {
        $doctor = $this->userWithRole('Doctor');

        $this->actingAs($doctor)->get('/docs/api.json')->assertForbidden();
    }
}
