<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_pending_account_cannot_sign_in(): void
    {
        $user = User::factory()->pending()->create();
        $user->assignRole('Nurse');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(403)
            ->assertJsonPath('account_status', User::STATUS_PENDING);
    }

    public function test_suspended_account_cannot_sign_in(): void
    {
        $user = User::factory()->suspended()->create();
        $user->assignRole('Nurse');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertStatus(403)
            ->assertJsonPath('account_status', User::STATUS_SUSPENDED);
    }

    public function test_disabled_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['account_status' => User::STATUS_DISABLED]);
        $user->assignRole('Nurse');

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_token_belonging_to_a_suspended_account_is_rejected(): void
    {
        $user = $this->userWithRole('Doctor');
        $token = $user->createToken('test')->plainTextToken;

        User::query()->whereKey($user->id)->update(['account_status' => User::STATUS_SUSPENDED]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403);
    }

    public function test_accounts_requiring_a_password_change_are_restricted(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $user->assignRole('Doctor');
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/patients')
            ->assertStatus(403)
            ->assertJsonPath('password_change_required', true);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }
}
