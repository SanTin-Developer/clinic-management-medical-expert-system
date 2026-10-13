<?php

namespace App\Services\Auth;

use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Notifications\EmailChangeCodeNotification;
use App\Notifications\EmailChangedNotice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Two-stage email change: the account owner re-authenticates, receives a code
 * at the new address, and only then is the address applied. Existing sessions
 * and trusted devices are revoked and the old address is notified.
 */
class EmailChangeService
{
    public function __construct(
        private readonly SecurityEventService $events,
    ) {}

    public function request(User $user, string $newEmail, string $password, Request $request): EmailChangeRequest
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The password is incorrect.'],
            ]);
        }

        $newEmail = strtolower(trim($newEmail));

        if ($newEmail === strtolower($user->email)) {
            throw ValidationException::withMessages([
                'new_email' => ['The new email address must be different from the current one.'],
            ]);
        }

        if (User::query()->where('email', $newEmail)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'new_email' => ['That email address is already in use.'],
            ]);
        }

        $user->emailChangeRequests()->whereNull('consumed_at')->delete();

        $length = 6;
        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        $change = $user->emailChangeRequests()->create([
            'new_email' => $newEmail,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => (int) config('auth.email_change.max_attempts', 5),
            'expires_at' => now()->addMinutes((int) config('auth.email_change.expire_minutes', 15)),
        ]);

        Notification::route('mail', $newEmail)->notify(new EmailChangeCodeNotification($code, $newEmail));

        $this->events->record(SecurityEventService::EMAIL_CHANGE_REQUESTED, $user, 'info', [
            'new_email' => $newEmail,
        ], $request);

        return $change;
    }

    public function confirm(User $user, string $code, Request $request): User
    {
        $change = $user->emailChangeRequests()->whereNull('consumed_at')->latest()->first();

        if ($change === null) {
            throw ValidationException::withMessages([
                'code' => ['No pending email change was found. Please start again.'],
            ]);
        }

        if ($change->isExpired()) {
            throw ValidationException::withMessages([
                'code' => ['This email change request has expired. Please start again.'],
            ]);
        }

        if ($change->hasExceededAttempts()) {
            throw ValidationException::withMessages([
                'code' => ['Too many incorrect attempts. Please start again.'],
            ]);
        }

        $change->increment('attempts');

        if (! Hash::check($code, $change->code_hash)) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is incorrect.'],
            ]);
        }

        if (User::query()->where('email', $change->new_email)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages([
                'code' => ['That email address is no longer available.'],
            ]);
        }

        $oldEmail = $user->email;

        $user->forceFill([
            'email' => $change->new_email,
            'email_verified_at' => now(),
        ])->save();

        $change->forceFill(['consumed_at' => now()])->save();

        // Changing the identifier invalidates all existing sessions/devices.
        $user->tokens()->delete();
        $user->trustedDevices()->delete();

        Notification::route('mail', $oldEmail)->notify(new EmailChangedNotice($oldEmail, $change->new_email));

        $this->events->record(SecurityEventService::EMAIL_CHANGED, $user, 'warning', [
            'old_email' => $oldEmail,
            'new_email' => $change->new_email,
        ], $request);

        return $user->refresh();
    }
}
