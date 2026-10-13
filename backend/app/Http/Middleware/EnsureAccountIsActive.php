<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject requests from accounts that are not in the active state. Pending,
 * suspended and disabled accounts cannot use the API even with a valid token.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->isActive()) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Your account is not active. Please contact your administrator.',
            'account_status' => $user->account_status,
        ], 403);
    }
}
