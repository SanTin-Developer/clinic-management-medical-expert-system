<?php

namespace App\Services\Auth;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TrustedDeviceService
{
    /**
     * Determine whether the current request originates from a device that the
     * user has previously chosen to trust.
     */
    public function isTrusted(Request $request, User $user): bool
    {
        return $this->match($request, $user) !== null;
    }

    /**
     * Resolve the trusted device referenced by the request cookie, validating
     * the token against the stored hash and the expiry timestamp.
     */
    public function match(Request $request, User $user): ?TrustedDevice
    {
        $token = $request->cookie((string) config('auth.otp.cookie_name', 'clinic_trusted_device'));

        if (! is_string($token) || $token === '') {
            return null;
        }

        $device = $user->trustedDevices()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($device === null) {
            return null;
        }

        if ($device->isExpired()) {
            $device->delete();

            return null;
        }

        $device->forceFill(['last_used_at' => now()])->save();

        return $device;
    }

    /**
     * Remember the current device for the user.
     *
     * @return array{device: TrustedDevice, cookie: string, minutes: int}
     */
    public function remember(Request $request, User $user): array
    {
        $token = Str::random(64);

        $device = $user->trustedDevices()->create([
            'device_id' => (string) Str::uuid(),
            'token_hash' => hash('sha256', $token),
            'name' => $this->describe($request),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'last_used_at' => now(),
            'expires_at' => now()->addDays((int) config('auth.otp.trusted_device_days', 30)),
        ]);

        return [
            'device' => $device,
            'cookie' => $token,
            'minutes' => (int) config('auth.otp.trusted_device_days', 30) * 24 * 60,
        ];
    }

    public function forgetAll(User $user): void
    {
        $user->trustedDevices()->delete();
    }

    /**
     * Build a short human-readable label from the request user agent.
     */
    private function describe(Request $request): string
    {
        $agent = (string) $request->userAgent();

        return $agent === '' ? 'Unknown device' : Str::limit($agent, 120, '…');
    }
}
