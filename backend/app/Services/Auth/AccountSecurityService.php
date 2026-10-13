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
