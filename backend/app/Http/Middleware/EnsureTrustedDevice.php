<?php

namespace App\Http\Middleware;

use App\Services\Auth\TrustedDeviceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrustedDevice
{
    public function __construct(
        private readonly TrustedDeviceService $trustedDevices,
    ) {}

    /**
     * Reject requests from devices that are not trusted.
     *
     * This guard is opt-in (AUTH_ENFORCE_TRUSTED_DEVICE=true). When enabled it
     * prevents a stolen bearer token from being replayed from an unknown device
     * because the matching HttpOnly device cookie is required as well.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! config('auth.otp.enforce_trusted_device', false)) {
            return $next($request);
        }

        if (! $this->trustedDevices->isTrusted($request, $user)) {
            return response()->json([
                'message' => 'Request rejected: unrecognized device. Please sign in again.',
            ], 403);
        }

        return $next($request);
    }
}
