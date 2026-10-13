<?php

namespace App\Http\Middleware;

use App\Services\Auth\StepUpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Require a valid, single-use step-up grant for a sensitive action. The grant
 * is passed in the X-Step-Up-Token header and must match the action named in
 * the middleware parameter (e.g. "step.up:email.change").
 */
class EnsureStepUp
{
    public function __construct(
        private readonly StepUpService $stepUp,
    ) {}

    public function handle(Request $request, Closure $next, string $action): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = $request->header('X-Step-Up-Token');

        if (! $this->stepUp->consume($user, $action, is_string($token) ? $token : null, $request)) {
            return response()->json([
                'message' => 'This action requires you to confirm your identity again.',
                'step_up_required' => true,
                'action' => $action,
            ], 403);
        }

        return $next($request);
    }
}
