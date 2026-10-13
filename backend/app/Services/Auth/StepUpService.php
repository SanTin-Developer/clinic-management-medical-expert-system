<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Server-side step-up authorization. Sensitive operations require the caller
 * to re-prove their identity; a short-lived grant is bound to the user and the
 * exact action it was issued for.
 */
class StepUpService
{
    public function __construct(
        private readonly SecurityEventService $events,
    ) {}

    /**
     * Authorize the action using the account password.
     *
     * @return array{ok: bool, message?: string, token?: string, expires_in?: int}
     */
    public function authorize(User $user, string $action, string $password, Request $request): array
    {
        if (! Hash::check($password, $user->password)) {
            $this->events->record(SecurityEventService::STEP_UP_DENIED, $user, 'warning', ['action' => $action], $request);

            return ['ok' => false, 'message' => 'The password is incorrect.'];
        }

        $token = Str::random(64);
        $ttl = (int) config('auth.step_up.ttl_minutes', 10);

        $user->stepUpAuthorizations()->create([
            'token_hash' => hash('sha256', $token),
            'action' => $action,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $this->events->record(SecurityEventService::STEP_UP_GRANTED, $user, 'info', ['action' => $action], $request);

        return ['ok' => true, 'token' => $token, 'expires_in' => $ttl * 60];
    }

    /**
     * Consume a step-up grant, marking it used so it cannot be replayed.
     */
    public function consume(User $user, string $action, ?string $token, Request $request): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $authorization = $user->stepUpAuthorizations()
            ->where('token_hash', hash('sha256', $token))
            ->where('action', $action)
            ->first();

        if ($authorization === null || $authorization->isConsumed() || $authorization->isExpired()) {
            return false;
        }

        $authorization->forceFill(['consumed_at' => now()])->save();

        $this->events->record(SecurityEventService::STEP_UP_USED, $user, 'info', ['action' => $action], $request);

        return true;
    }
}
