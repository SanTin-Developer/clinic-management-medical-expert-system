<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class UserTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $this->actingAsRole('Admin');

        $this->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@clinic.local',
            'password' => 'Password@12345',
            'role' => 'Nurse',
        ])->assertCreated()
            ->assertJsonPath('user.email', 'jane@clinic.local');

        $this->assertDatabaseHas('users', ['email' => 'jane@clinic.local']);
    }

    public function test_admin_can_list_users(): void
    {
        $this->actingAsRole('Admin');

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_doctor_cannot_list_users(): void
    {
        $this->actingAsRole('Doctor');

        $this->getJson('/api/v1/users')->assertForbidden();
    }

    public function test_user_creation_requires_a_valid_role(): void
    {
        $this->actingAsRole('Admin');

        $this->postJson('/api/v1/users', [
            'name' => 'No Role',
            'email' => 'norole@clinic.local',
            'password' => 'Password@12345',
            'role' => 'Wizard',
        ])->assertStatus(422)->assertJsonValidationErrors(['role']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }
}
