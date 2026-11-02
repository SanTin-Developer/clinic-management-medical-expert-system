<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * TEMPORARY diagnostic endpoint — remove after verifying Redis connectivity.
 *
 * Exposes a single Admin-only health check for the *actual* configured default
 * Redis connection. It exists because the deployment platform offers no shell
 * access, so there is otherwise no way to confirm the app can reach Redis.
 *
 * Security notes:
 *  - Gated by the `system.health.view` permission, which only the Admin role
 *    holds (see RolePermissionSeeder). The route itself sits behind
 *    `auth:sanctum`, so unauthenticated callers get 401 and non-admins 403.
 *  - The response is a fixed shape (`status`, `connected`, and `message` only
 *    on failure). It never includes the host, port, username, password, DSN,
 *    exception class, or stack trace.
 *  - The raw exception is deliberately NOT passed to `report()`: phpredis
 *    messages can embed the hostname, and this endpoint's whole purpose is to
 *    avoid leaking connection internals.
 *
 * Remove this controller, its route, and RedisHealthTest once connectivity is
 * confirmed. See docs/10-deployment for the removal steps.
 */
class RedisHealthController extends Controller
{
    public function check(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.health.view'), 403, 'This action is unauthorized.');

        try {
            // Resolve and ping the real configured connection. `connection()`
            // establishes the link eagerly, so a failure throws here rather than
            // returning a connection object that silently fails later.
            $ping = Redis::connection('default')->ping();
            $connected = in_array($ping, [true, 'PONG', '+PONG'], true);
        } catch (Throwable) {
            return $this->failed();
        }

        if (! $connected) {
            return $this->failed();
        }

        return response()->json([
            'status' => 'ok',
            'connected' => true,
        ]);
    }

    /**
     * Fixed failure payload. The message is a constant so no host, credential,
     * DSN or exception detail can ever reach the response.
     */
    private function failed(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'connected' => false,
            'message' => 'Redis connection failed.',
        ]);
    }
}
