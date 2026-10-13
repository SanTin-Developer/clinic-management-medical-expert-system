<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When mandatory two-factor is enabled, staff accounts that have not yet
 * enrolled are blocked from the API except for the endpoints they need to
 * complete enrolment. Patients are exempt because the policy targets staff.
 */
class EnsureTwoFactorEnrolled
{
    /**
     * Routes that remain reachable while enrolment is pending.
     *
     * @var array<int, string>
     */
    private const ALLOWED = [
        'api/v1/auth/me',
        'api/v1/auth/logout',
        'api/v1/auth/2fa/*',
        'api/v1/auth/password',
        'api/v1/auth/step-up',
        'api/v1/auth/devices*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.two_factor.mandatory', false)) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || $user->hasRole('Patient') || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->is(...self::ALLOWED)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Two-factor authentication must be enabled before continuing.',
            'two_factor_setup_required' => true,
        ], 403);
    }
}
