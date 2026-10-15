<?php

namespace Tests\Feature;

use App\Services\Auth\SecurityEventService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
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

    public function test_user_store_returns_422_when_a_password_is_supplied(): void
    {
        $this->actingAsRole('Admin');

        $this->postJson('/api/v1/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@clinic.local',
            'password' => 'Password@12345',
            'role' => 'Nurse',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'jane@clinic.local']);
        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventService::USER_PASSWORD_CHANGE_REJECTED,
        ]);
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

    public function test_user_store_returns_422_without_a_password(): void
    {
        $this->actingAsRole('Admin');

        $this->postJson('/api/v1/users', [
            'name' => 'No Role',
            'email' => 'norole@clinic.local',
            'role' => 'Nurse',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('account');

        $this->assertDatabaseMissing('users', ['email' => 'norole@clinic.local']);
    }

    public function test_admin_can_update_permitted_user_profile_fields(): void
    {
        $this->actingAsRole('Admin');
        $user = $this->userWithRole('Nurse');

        $this->putJson("/api/v1/users/{$user->id}", [
            'name' => 'Updated Nurse',
            'username' => 'updated.nurse',
            'email' => 'updated-nurse@clinic.local',
            'role' => 'Doctor',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Updated Nurse')
            ->assertJsonPath('user.roles.0', 'Doctor');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Nurse',
            'username' => 'updated.nurse',
            'email' => 'updated-nurse@clinic.local',
        ]);
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => SecurityEventService::USER_ROLE_CHANGED,
        ]);
    }

    public function test_user_update_returns_422_and_preserves_password_when_an_admin_supplies_a_password(): void
    {
        $this->actingAsRole('Admin');
        $user = $this->userWithRole('Nurse');
        $originalHash = $user->password;

        $this->putJson("/api/v1/users/{$user->id}", [
            'password' => 'Replacement@123',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('password');

        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => SecurityEventService::USER_PASSWORD_CHANGE_REJECTED,
        ]);
    }

    public function test_user_update_returns_422_when_a_non_admin_attempts_role_escalation(): void
    {
        Role::findByName('Head Nurse')->givePermissionTo('users.update');
        $actor = $this->userWithRole('Head Nurse');
        $target = $this->userWithRole('Nurse');
        $this->actingAs($actor);

        $this->putJson("/api/v1/users/{$target->id}", [
            'role' => 'Admin',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        $this->assertFalse($target->fresh()->hasRole('Admin'));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
    }
}
