<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\AccountStatusChangedNotification;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Account lifecycle and credential operations shared by the security
 * controllers.
 */
class AccountSecurityService
{
    public function __construct(
        private readonly SecurityEventService $events,
    ) {}

    public function changePassword(User $user, string $currentPassword, string $newPassword, Request $request, bool $revokeSessions = true): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The new password must be different from the current password.'],
            ]);
        }

        $user->forceFill([
            'password' => $newPassword,
            'password_changed_at' => now(),
            'must_change_password' => false,
            'remember_token' => null,
        ])->save();

        if ($revokeSessions) {
            $user->tokens()->delete();
            $user->trustedDevices()->delete();
        }

        $user->notify(new PasswordChangedNotification);

        $this->events->record(SecurityEventService::PASSWORD_CHANGED, $user, 'info', [], $request);
    }

    public function suspend(User $user, ?string $reason, Request $request): void
    {
        $this->assertNotLastActiveAdmin($user);

        $user->forceFill([
            'account_status' => User::STATUS_SUSPENDED,
            'suspended_at' => now(),
            'suspended_reason' => $reason,
        ])->save();

        $user->tokens()->delete();
        $user->trustedDevices()->delete();

        $user->notify(new AccountStatusChangedNotification('suspended', $reason));

        $this->events->record(SecurityEventService::ACCOUNT_SUSPENDED, $user, 'warning', [
            'reason' => $reason,
        ], $request);
    }

    public function activate(User $user, Request $request): void
    {
        // The approval workflow is the only path that moves a freshly
        // registered ("pending_approval") or previously rejected account into
        // the active state, so that the reviewing administrator and decision
        // are always recorded. Route those through approve() instead.
        if ($user->isPendingApproval() || $user->isRejected()) {
            throw ValidationException::withMessages([
                'account' => ['This account must be reviewed through the approval workflow before it can be activated.'],
            ]);
        }

        $wasSuspended = $user->isSuspended();

        $user->forceFill([
            'account_status' => User::STATUS_ACTIVE,
            'suspended_at' => null,
            'suspended_reason' => null,
            'activated_at' => $user->activated_at ?? now(),
        ])->save();

        $user->notify(new AccountStatusChangedNotification('active'));

        $this->events->record(
            $wasSuspended ? SecurityEventService::ACCOUNT_REACTIVATED : SecurityEventService::ACCOUNT_ACTIVATED,
            $user,
            'info',
            [],
            $request,
        );
    }

    /**
     * Approve a staff account that submitted a registration. This is the only
     * transition that makes a "pending_approval" (or previously "rejected")
     * account eligible to authenticate. The reviewing administrator and the
     * decision timestamp are recorded for audit.
     */
    public function approve(User $admin, User $target, Request $request): void
    {
        $this->assertNotSelf($admin, $target, 'approve');

        if (! $target->isPendingApproval() && ! $target->isRejected()) {
            throw ValidationException::withMessages([
                'account' => ['This account is not awaiting approval.'],
            ]);
        }

        $target->forceFill([
            'account_status' => User::STATUS_ACTIVE,
            'activated_at' => now(),
            'approved_at' => now(),
            'approved_by' => $admin->id,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'suspended_at' => null,
            'suspended_reason' => null,
        ])->save();

        // A pending/rejected account could never hold a usable session, but
        // clear any stale tokens and trusted devices defensively so a grant
        // issued before this workflow cannot be replayed.
        $target->tokens()->delete();
        $target->trustedDevices()->delete();

        $target->notify(new AccountStatusChangedNotification('approved'));

        $this->events->record(SecurityEventService::ACCOUNT_APPROVED, $target, 'info', [
            'approved_by' => $admin->id,
        ], $request);
    }

    /**
     * Reject a staff account that submitted a registration. A reason is
     * mandatory. The account is moved to "rejected", which — like every
     * non-active state — cannot authenticate or use an existing token.
     */
    public function reject(User $admin, User $target, string $reason, Request $request): void
    {
        $this->assertNotSelf($admin, $target, 'reject');

        if (! $target->isPendingApproval() && ! $target->isRejected()) {
            throw ValidationException::withMessages([
                'account' => ['This account is not awaiting approval.'],
            ]);
        }

        $target->forceFill([
            'account_status' => User::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $admin->id,
            'rejection_reason' => $reason,
            'approved_at' => null,
            'approved_by' => null,
        ])->save();

        $target->tokens()->delete();
        $target->trustedDevices()->delete();

        $target->notify(new AccountStatusChangedNotification('rejected', $reason));

        $this->events->record(SecurityEventService::ACCOUNT_REJECTED, $target, 'warning', [
            'rejected_by' => $admin->id,
            'reason' => $reason,
        ], $request);
    }

    public function disable(User $user, Request $request): void
    {
        $this->assertNotLastActiveAdmin($user);

        $user->forceFill([
            'account_status' => User::STATUS_DISABLED,
            'suspended_at' => now(),
        ])->save();

        $user->tokens()->delete();
        $user->trustedDevices()->delete();

        $user->notify(new AccountStatusChangedNotification('disabled'));

        $this->events->record(SecurityEventService::ACCOUNT_DISABLED, $user, 'warning', [], $request);
    }

    /**
     * An administrator must not approve or reject their own account.
     */
    private function assertNotSelf(User $admin, User $target, string $action): void
    {
        if ($admin->id === $target->id) {
            throw ValidationException::withMessages([
                'account' => ["You cannot {$action} your own account."],
            ]);
        }
    }

    /**
     * Prevent the system from being locked out by removing every active admin.
     */
    private function assertNotLastActiveAdmin(User $target): void
    {
        if (! $target->hasRole('Admin') || $target->isSuspended() || $target->isDisabled()) {
            return;
        }

        $remaining = User::query()
            ->role('Admin')
            ->whereKeyNot($target->id)
            ->where('account_status', User::STATUS_ACTIVE)
            ->count();

        if ($remaining === 0) {
            throw ValidationException::withMessages([
                'account' => ['The last active administrator account cannot be suspended or disabled.'],
            ]);
        }
    }
}
