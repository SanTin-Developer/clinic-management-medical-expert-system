<?php

namespace Tests\Feature;

use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use App\Services\Auth\SecurityEventService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class StaffApprovalTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private ?string $lastInvitationToken = null;

    /**
     * Invite a staff member and return the created invitation, capturing the
     * plaintext token so the test can drive the public acceptance endpoint.
     */
    private function invite(string $email = 'pending.nurse@clinic.local', string $role = 'Nurse'): StaffInvitation
    {
        Notification::fake();

        $captured = null;

        $this->postJson('/api/v1/staff/invitations', [
            'name' => 'Pending Nurse',
            'email' => $email,
            'role' => $role,
        ])->assertStatus(201);

        $invitation = StaffInvitation::where('email', $email)->latest('id')->firstOrFail();

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

    /**
     * Drive the public invitation acceptance so the account is awaiting approval.
     */
    private function acceptInvitation(string $password = 'Nurse@12345'): User
    {
        $this->postJson('/api/v1/auth/invitation/accept', [
            'token' => $this->lastInvitationToken,
            'password' => $password,
            'password_confirmation' => $password,
        ])->assertOk();

        return User::where('email', 'pending.nurse@clinic.local')->firstOrFail();
    }

    private function stepUpToken(string $action): string
    {
        return $this->postJson('/api/v1/auth/step-up', [
            'action' => $action,
            'password' => 'password',
        ])
            ->assertOk()
            ->json('step_up_token');
    }

    public function test_accepted_invitation_lands_in_pending_approval_and_cannot_sign_in(): void
    {
        $this->actingAsRole('Admin');
        $this->invite();
        $user = $this->acceptInvitation();

        $this->assertTrue($user->isPendingApproval());
        $this->assertFalse($user->isActive());

        // No usable session/token can be obtained while awaiting approval.
        $this->app['auth']->shouldUse('web');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'pending.nurse@clinic.local',
            'password' => 'Nurse@12345',
        ])
            ->assertStatus(403)
            ->assertJsonPath('account_status', User::STATUS_PENDING_APPROVAL);
    }

    public function test_pending_approval_token_cannot_reach_protected_endpoints(): void
    {
        $user = User::factory()->pendingApproval()->create();
        $user->assignRole('Nurse');

        // Even a token minted directly on the model must be rejected by the
        // account.active middleware on every protected route.
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('account_status', User::STATUS_PENDING_APPROVAL);
    }

    public function test_rejected_token_cannot_reach_protected_endpoints(): void
    {
        $user = User::factory()->rejected()->create();
        $user->assignRole('Nurse');

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('account_status', User::STATUS_REJECTED);
    }

    public function test_admin_can_approve_a_pending_account_and_it_can_then_sign_in(): void
    {
        $admin = $this->actingAsRole('Admin');
        $target = User::factory()->pendingApproval()->create();
        $target->assignRole('Nurse');
        $target->forceFill(['password' => bcrypt('Nurse@12345')])->save();

        $stepUp = $this->stepUpToken('users.approve');

        $this->postJson("/api/v1/users/{$target->id}/approve", [], ['X-Step-Up-Token' => $stepUp])
            ->assertOk();

        $target->refresh();
        $this->assertTrue($target->isActive());
        $this->assertNotNull($target->approved_at);
        $this->assertSame($admin->id, $target->approved_by);

        // After approval the account can authenticate (subject to 2FA/OTP).
        $this->app['auth']->shouldUse('web');

        $this->postJson('/api/v1/auth/login', [
            'email' => $target->email,
            'password' => 'Nurse@12345',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_approving_records_an_audit_security_event(): void
    {
        $this->actingAsRole('Admin');
        $target = User::factory()->pendingApproval()->create();
        $target->assignRole('Nurse');

        $stepUp = $this->stepUpToken('users.approve');
        $this->postJson("/api/v1/users/{$target->id}/approve", [], ['X-Step-Up-Token' => $stepUp])->assertOk();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $target->id,
            'event' => SecurityEventService::ACCOUNT_APPROVED,
        ]);
    }

    public function test_admin_can_reject_a_pending_account_with_a_reason(): void
    {
        $admin = $this->actingAsRole('Admin');
        $target = User::factory()->pendingApproval()->create();
        $target->assignRole('Nurse');

        $stepUp = $this->stepUpToken('users.approve');

        $this->postJson("/api/v1/users/{$target->id}/reject", [
            'reason' => 'Credential could not be verified',
        ], ['X-Step-Up-Token' => $stepUp])->assertOk();

        $target->refresh();
        $this->assertTrue($target->isRejected());
        $this->assertSame('Credential could not be verified', $target->rejection_reason);
        $this->assertSame($admin->id, $target->rejected_by);
        $this->assertNotNull($target->rejected_at);

        // A rejected account cannot sign in.
        $this->app['auth']->shouldUse('web');

        $this->postJson('/api/v1/auth/login', [
            'email' => $target->email,
            'password' => 'password',
        ])->assertStatus(403)->assertJsonPath('account_status', User::STATUS_REJECTED);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $this->actingAsRole('Admin');
        $target = User::factory()->pendingApproval()->create();
        $target->assignRole('Nurse');

        $stepUp = $this->stepUpToken('users.approve');

        $this->postJson("/api/v1/users/{$target->id}/reject", [], ['X-Step-Up-Token' => $stepUp])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertTrue($target->fresh()->isPendingApproval());
    }

    public function test_rejection_records_an_audit_security_event(): void
    {
        $this->actingAsRole('Admin');
        $target = User::factory()->pendingApproval()->create();
        $target->assignRole('Nurse');

        $stepUp = $this->stepUpToken('users.approve');
        $this->postJson("/api/v1/users/{$target->id}/reject", ['reason' => 'Not a clinic employee'], ['X-Step-Up-Token' => $stepUp])
            ->assertOk();

        $this->assertDatabaseHas('security_events', [
            'user_id' => $target->id,
            'event' => SecurityEventService::ACCOUNT_REJECTED,
        ]);
    }

    public function test_a_rejected_account_can_be_approved_later(): void
    {
        $admin = $this->actingAsRole('Admin');
        $target = User::factory()->rejected()->create();
        $target->assignRole('Nurse');

        $stepUp = $this->stepUpToken('users.approve');
        $this->postJson("/api/v1/users/{$target->id}/approve", [], ['X-Step-Up-Token' => $stepUp])->assertOk();

        $target->refresh();
        $this->assertTrue($target->isActive());
        $this->assertNull($target->rejection_reason);
        $this->assertSame($admin->id, $target->approved_by);
    }

    public function test_non_admin_cannot_approve_or_reject(): void
    {
        $this->actingAsRole('Doctor');
        $target = User::factory()->pendingApproval()->create();
        $target->assignRole('Nurse');

        $this->postJson("/api/v1/users/{$target->id}/approve")->assertStatus(403);
        $this->postJson("/api/v1/users/{$target->id}/reject", ['reason' => 'nope'])->assertStatus(403);

        $this->assertTrue($target->fresh()->isPendingApproval());
    }

    public function test_a_user_without_the_approve_permission_cannot_open_the_queue(): void
    {
        $this->actingAsRole('Receptionist');

        $this->getJson('/api/v1/staff/approvals')->assertStatus(403);
    }

    public function test_admin_cannot_approve_their_own_account(): void
    {
        // An active administrator must not be able to approve their own account.
        $admin = $this->actingAsRole('Admin');

        $stepUp = $this->stepUpToken('users.approve');

        $this->postJson("/api/v1/users/{$admin->id}/approve", [], ['X-Step-Up-Token' => $stepUp])
            ->assertStatus(422);

        $this->assertTrue($admin->fresh()->isActive());
    }

    public function test_approving_requires_step_up(): void
    {
        $this->actingAsRole('Admin');
        $target = User::factory()->pendingApproval()->create();
        $target->assignRole('Nurse');

        $this->postJson("/api/v1/users/{$target->id}/approve")
            ->assertStatus(403)
            ->assertJsonPath('step_up_required', true);

        $this->assertTrue($target->fresh()->isPendingApproval());
    }

    public function test_pending_account_cannot_be_activated_outside_the_approval_workflow(): void
    {
        $this->actingAsRole('Admin');
        $target = User::factory()->pendingApproval()->create();
        $target->assignRole('Nurse');

        // The generic activate endpoint must refuse to shortcut the workflow.
        $this->postJson("/api/v1/users/{$target->id}/activate")->assertStatus(422);

        $this->assertTrue($target->fresh()->isPendingApproval());
    }

    public function test_approvals_index_lists_only_pending_accounts(): void
    {
        $this->actingAsRole('Admin');

        $pending = User::factory()->pendingApproval()->create();
        $pending->assignRole('Nurse');
        User::factory()->create()->assignRole('Doctor'); // active, must not appear

        $response = $this->getJson('/api/v1/staff/approvals')->assertOk();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($pending->id));
        $this->assertCount(1, $ids);
    }

    public function test_full_invite_accept_approve_flow(): void
    {
        $this->actingAsRole('Admin');
        $this->invite();
        $user = $this->acceptInvitation();

        $this->assertTrue($user->isPendingApproval());

        $stepUp = $this->stepUpToken('users.approve');
        $this->postJson("/api/v1/users/{$user->id}/approve", [], ['X-Step-Up-Token' => $stepUp])->assertOk();

        $this->app['auth']->shouldUse('web');
        $this->postJson('/api/v1/auth/login', [
            'email' => 'pending.nurse@clinic.local',
            'password' => 'Nurse@12345',
        ])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_existing_active_users_and_permissions_are_unaffected(): void
    {
        // A receptionist can still create patients after the workflow change.
        $this->actingAsRole('Receptionist');

        $this->postJson('/api/v1/patients', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'female',
            'phone_number' => '012345678',
        ])->assertStatus(201);
    }
}
