<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Force accounts flagged with must_change_password to change their password
 * before using the rest of the API. A small allowlist keeps the account and
 * security endpoints reachable so the user can actually comply.
 */
class EnsurePasswordIsCurrent
{
    /**
     * Routes that remain reachable while a password change is required.
     *
     * @var array<int, string>
     */
    private const ALLOWED = [
        'api/v1/auth/me',
        'api/v1/auth/logout',
        'api/v1/auth/password',
        'api/v1/auth/2fa/*',
        'api/v1/auth/step-up',
        'api/v1/auth/devices*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->is(...self::ALLOWED)) {
            return $next($request);
        }

        return response()->json([
            'message' => 'You must change your password before continuing.',
            'password_change_required' => true,
        ], 403);
    }
}
