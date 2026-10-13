<?php

namespace Tests\Feature;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class StaffProvisioningTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function invite(?string $token = null): StaffInvitation
    {
        Notification::fake();

        $captured = null;

        $response = $this->postJson('/api/v1/staff/invitations', [
            'name' => 'New Nurse',
            'email' => 'new.nurse@clinic.local',
            'role' => 'Nurse',
        ])->assertStatus(201);

        $invitation = StaffInvitation::findOrFail($response->json('invitation.id'));

        Notification::assertSentTo(
            User::findOrFail($invitation->user_id),
            StaffInvitationNotification::class,
            function (StaffInvitationNotification $notification) use (&$captured) {
                $captured = $notification->token;

                return true;
            },
        );

        $this->lastInvitationToken = $captured;

        return $invitation;
    }

    private ?string $lastInvitationToken = null;

    private function stepUpToken(string $action): string
    {
        return $this->postJson('/api/v1/auth/step-up', [
            'action' => $action,
            'password' => 'password',
        ])
            ->assertOk()
            ->json('step_up_token');
    }

    /**
     * The default auth guard can leak to "sanctum" across requests within a
     * single test after an authenticated call; restore it before exercising the
     * password login endpoint, which relies on the "web" guard.
     */
    private function useWebGuard(): void
    {
        $this->app['auth']->shouldUse('web');
    }

    public function test_admin_can_invite_a_staff_member(): void
    {
        $this->actingAsRole('Admin');

        $invitation = $this->invite();

        $this->assertNotNull($this->lastInvitationToken);
        $this->assertSame('new.nurse@clinic.local', $invitation->email);

        $pending = User::findOrFail($invitation->user_id);
        $this->assertTrue($pending->isPending());
        $this->assertNotEmpty($this->lastInvitationToken);
    }

    public function test_non_admin_cannot_invite_staff(): void
    {
        $this->actingAsRole('Doctor');

        $this->postJson('/api/v1/staff/invitations', [
            'name' => 'X',
            'email' => 'x@clinic.local',
            'role' => 'Nurse',
        ])->assertStatus(403);
    }

    public function test_invitation_can_be_looked_up_and_accepted(): void
    {
        $this->actingAsRole('Admin');
        $this->invite();

        $token = $this->lastInvitationToken;

        $this->postJson('/api/v1/auth/invitation/lookup', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('role', 'Nurse');

        $this->postJson('/api/v1/auth/invitation/accept', [
            'token' => $token,
            'password' => 'Nurse@12345',
            'password_confirmation' => 'Nurse@12345',
        ])->assertOk();

        $user = User::where('email', 'new.nurse@clinic.local')->firstOrFail();

        $this->assertTrue($user->isActive());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('Nurse'));

        // the new account can sign in
        $this->useWebGuard();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'new.nurse@clinic.local',
            'password' => 'Nurse@12345',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_invalid_invitation_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/invitation/accept', [
            'token' => 'not-a-real-token',
            'password' => 'Nurse@12345',
            'password_confirmation' => 'Nurse@12345',
        ])->assertStatus(422);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $this->actingAsRole('Admin');
        $invitation = $this->invite();
        $invitation->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->postJson('/api/v1/auth/invitation/accept', [
            'token' => $this->lastInvitationToken,
            'password' => 'Nurse@12345',
            'password_confirmation' => 'Nurse@12345',
        ])->assertStatus(422);
    }

    public function test_admin_can_revoke_an_invitation(): void
    {
        $this->actingAsRole('Admin');
        $invitation = $this->invite();

        $this->deleteJson("/api/v1/staff/invitations/{$invitation->id}")
            ->assertOk();

        $this->postJson('/api/v1/auth/invitation/lookup', ['token' => $this->lastInvitationToken])
            ->assertStatus(422);
    }

    public function test_admin_with_step_up_can_suspend_a_staff_member(): void
    {
        $admin = $this->actingAsRole('Admin');
        $target = $this->userWithRole('Doctor');

        $token = $this->stepUpToken('users.suspend');

        $this->postJson("/api/v1/users/{$target->id}/suspend", [
            'reason' => 'policy violation',
        ], ['X-Step-Up-Token' => $token])->assertOk();

        $target->refresh();
        $this->assertTrue($target->isSuspended());
        $this->assertSame('policy violation', $target->suspended_reason);

        $this->useWebGuard();

        $this->postJson('/api/v1/auth/login', [
            'email' => $target->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_suspend_requires_step_up(): void
    {
        $this->actingAsRole('Admin');
        $target = $this->userWithRole('Doctor');

        $this->postJson("/api/v1/users/{$target->id}/suspend")
            ->assertStatus(403)
            ->assertJsonPath('step_up_required', true);

        $this->assertFalse($target->fresh()->isSuspended());
    }

    public function test_the_last_active_admin_cannot_be_suspended(): void
    {
        $admin = $this->actingAsRole('Admin');

        $token = $this->stepUpToken('users.suspend');

        $this->postJson("/api/v1/users/{$admin->id}/suspend", [], ['X-Step-Up-Token' => $token])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->isActive());
    }
}
