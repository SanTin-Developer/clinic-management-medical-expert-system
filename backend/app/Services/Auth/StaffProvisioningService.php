<?php

namespace App\Services\Auth;

use App\Models\Staff;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Administrators provision staff accounts by invitation. A pending user row is
 * created and only becomes active once the invited person accepts the emailed
 * token and sets their own password and second factor.
 */
class StaffProvisioningService
{
    public function __construct(
        private readonly SecurityEventService $events,
    ) {}

    /**
     * Create (or reuse) a pending staff account and email an invitation.
     *
     * @param  array{name: string, email: string, role: string, staff_id?: ?int}  $data
     * @return array{invitation: StaffInvitation, token: string}
     */
    public function invite(User $inviter, array $data, Request $request): array
    {
        $email = strtolower(trim($data['email']));

        $user = User::query()->where('email', $email)->first();

        if ($user !== null && ! $user->isPending()) {
            throw ValidationException::withMessages([
                'email' => ['An account with this email address already exists.'],
            ]);
        }

        $staff = null;
        if (! empty($data['staff_id'])) {
            $staff = Staff::find($data['staff_id']);
            if ($staff === null) {
                throw ValidationException::withMessages([
                    'staff_id' => ['The selected staff profile does not exist.'],
                ]);
            }
            if ($staff->user_id !== null && $staff->user_id !== ($user?->id)) {
                throw ValidationException::withMessages([
                    'staff_id' => ['The selected staff member already has a linked user account.'],
                ]);
            }
        }

        if ($user === null) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $this->generateUsername($email),
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
                'account_status' => User::STATUS_PENDING,
            ]);
        }

        StaffInvitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $token = Str::random(64);

        $invitation = StaffInvitation::create([
            'staff_id' => $staff?->id,
            'user_id' => $user->id,
            'invited_by' => $inviter->id,
            'email' => $email,
            'name' => $data['name'],
            'role' => $data['role'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours((int) config('auth.invitation.expire_hours', 72)),
        ]);

        $user->notify(new StaffInvitationNotification($invitation, $token));

        $this->events->record(SecurityEventService::ACCOUNT_INVITED, $user, 'info', [
            'role' => $data['role'],
            'invited_by' => $inviter->id,
        ], $request);

        return ['invitation' => $invitation, 'token' => $token];
    }

    public function findByToken(string $token): ?StaffInvitation
    {
        return StaffInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * Accept an invitation: capture the chosen password and profile, assign the
     * invited role, and move the account into "pending_approval". Acceptance no
     * longer grants application access — an authorized administrator must
     * approve the account (see AccountSecurityService::approve) before it can
     * authenticate.
     *
     * @param  array{name?: string, password: string, username?: string}  $data
     */
    public function accept(string $token, array $data, Request $request): User
    {
        $invitation = $this->findByToken($token);

        if ($invitation === null || ! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => ['This invitation is invalid, expired or has already been used.'],
            ]);
        }

        $user = $invitation->user;

        if ($user === null) {
            throw ValidationException::withMessages([
                'token' => ['This invitation is no longer valid.'],
            ]);
        }

        $username = ! empty($data['username'])
            ? strtolower(trim($data['username']))
            : ($user->username ?: $this->generateUsername($user->email));

        if (User::query()->where('username', $username)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'username' => ['That username is already taken.'],
            ]);
        }

        $user->forceFill([
            'name' => $data['name'] ?? $invitation->name,
            'username' => $username,
            'password' => $data['password'],
            'account_status' => User::STATUS_PENDING_APPROVAL,
            'activated_at' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'suspended_at' => null,
            'suspended_reason' => null,
        ])->save();

        $user->syncRoles([$invitation->role]);

        $invitation->forceFill(['accepted_at' => now()])->save();

        if ($invitation->staff_id) {
            $staff = Staff::find($invitation->staff_id);
            if ($staff) {
                $staff->update(['user_id' => $user->id]);
                $user->forceFill(['staff_id' => $staff->staff_number])->save();
            }
        }

        $this->events->record(SecurityEventService::ACCOUNT_REGISTRATION_SUBMITTED, $user, 'info', [
            'role' => $invitation->role,
            'staff_id' => $invitation->staff_id,
        ], $request);

        return $user;
    }

    public function revoke(StaffInvitation $invitation, Request $request): void
    {
        if (! $invitation->isPending()) {
            return;
        }

        $invitation->forceFill(['revoked_at' => now()])->save();

        $this->events->record(SecurityEventService::ACCOUNT_DISABLED, $invitation->user, 'warning', [
            'reason' => 'invitation_revoked',
        ], $request);
    }

    /**
     * Re-issue an invitation token and email it again.
     *
     * @return array{invitation: StaffInvitation, token: string}
     */
    public function resend(StaffInvitation $invitation, Request $request): array
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'invitation' => ['This invitation has already been accepted.'],
            ]);
        }

        $token = Str::random(64);

        $invitation->forceFill([
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours((int) config('auth.invitation.expire_hours', 72)),
            'revoked_at' => null,
        ])->save();

        $invitation->user?->notify(new StaffInvitationNotification($invitation, $token));

        return ['invitation' => $invitation, 'token' => $token];
    }

    private function generateUsername(string $email): string
    {
        $base = strstr($email, '@', true) ?: $email;
        $base = strtolower($base);
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?? '';
        $base = trim($base, '._-') ?: 'user';

        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
