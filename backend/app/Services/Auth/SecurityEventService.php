<?php

namespace App\Services\Auth;

use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Records authentication and account security events. The log is append-only
 * and never stores passwords, tokens or other secrets.
 */
class SecurityEventService
{
    public const LOGIN_SUCCEEDED = 'login.succeeded';

    public const LOGIN_FAILED = 'login.failed';

    public const LOGIN_BLOCKED = 'login.blocked';

    public const LOGIN_LOCKED = 'login.locked';

    public const LOGOUT = 'logout';

    public const PASSWORD_CHANGED = 'password.changed';

    public const TWO_FACTOR_ENROLLED = 'two_factor.enrolled';

    public const TWO_FACTOR_CONFIRMED = 'two_factor.confirmed';

    public const TWO_FACTOR_DISABLED = 'two_factor.disabled';

    public const TWO_FACTOR_FAILED = 'two_factor.failed';

    public const TWO_FACTOR_CHALLENGE_ISSUED = 'two_factor.challenge_issued';

    public const RECOVERY_CODES_REGENERATED = 'recovery_codes.regenerated';

    public const RECOVERY_CODE_USED = 'recovery_code.used';

    public const TRUSTED_DEVICE_ADDED = 'trusted_device.added';

    public const TRUSTED_DEVICE_REVOKED = 'trusted_device.revoked';

    public const TRUSTED_DEVICES_REVOKED_ALL = 'trusted_device.revoked_all';

    public const EMAIL_CHANGE_REQUESTED = 'email_change.requested';

    public const EMAIL_CHANGED = 'email.changed';

    public const ACCOUNT_INVITED = 'account.invited';

    public const ACCOUNT_ACTIVATED = 'account.activated';

    public const ACCOUNT_SUSPENDED = 'account.suspended';

    public const ACCOUNT_REACTIVATED = 'account.reactivated';

    public const ACCOUNT_DISABLED = 'account.disabled';

    public const STEP_UP_GRANTED = 'step_up.granted';

    public const STEP_UP_DENIED = 'step_up.denied';

    public const STEP_UP_USED = 'step_up.used';

    /**
     * Severity assigned to each event family when not overridden.
     *
     * @var array<string, string>
     */
    private const SEVERITY = [
        self::LOGIN_FAILED => 'warning',
        self::LOGIN_BLOCKED => 'warning',
        self::LOGIN_LOCKED => 'critical',
        self::TWO_FACTOR_FAILED => 'warning',
        self::TWO_FACTOR_DISABLED => 'warning',
        self::RECOVERY_CODE_USED => 'warning',
        self::ACCOUNT_SUSPENDED => 'warning',
        self::ACCOUNT_DISABLED => 'warning',
        self::EMAIL_CHANGED => 'warning',
        self::STEP_UP_DENIED => 'warning',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $event,
        ?User $user = null,
        string $severity = 'info',
        array $context = [],
        ?Request $request = null,
    ): SecurityEvent {
        $severity = self::SEVERITY[$event] ?? $severity;

        return SecurityEvent::create([
            'user_id' => $user?->id,
            'event' => $event,
            'severity' => $severity,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 1000, '') : null,
            'context' => $context === [] ? null : $this->sanitize($context),
            'created_at' => now(),
        ]);
    }

    /**
     * A coarse risk signal used to flag suspicious sign-ins. It is advisory:
     * authorization decisions never depend on it.
     */
    public function riskScore(Request $request, ?User $user = null): int
    {
        $score = (int) ($request->header('X-Forwarded-For') !== null ? 1 : 0);

        if ($user !== null) {
            $knownIp = $user->trustedDevices()
                ->where('ip_address', $request->ip())
                ->exists();

            if (! $knownIp) {
                $score += 1;
            }

            $recentFailures = $user->securityEvents()
                ->where('event', self::LOGIN_FAILED)
                ->where('created_at', '>=', now()->subHour())
                ->count();

            $score += min(2, intdiv($recentFailures, 3));
        }

        return $score;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function sanitize(array $context): array
    {
        $blocked = ['password', 'password_confirmation', 'token', 'secret', 'code', 'two_factor_secret'];

        return collect($context)
            ->reject(fn ($value, $key) => is_string($key) && in_array(strtolower($key), $blocked, true))
            ->all();
    }
}
