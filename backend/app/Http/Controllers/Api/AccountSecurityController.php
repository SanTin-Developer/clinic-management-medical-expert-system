<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ConfirmEmailChangeRequest;
use App\Http\Requests\Auth\RequestEmailChangeRequest;
use App\Http\Requests\Auth\StepUpRequest;
use App\Services\Auth\AccountSecurityService;
use App\Services\Auth\EmailChangeService;
use App\Services\Auth\StepUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSecurityController extends Controller
{
    public function __construct(
        private readonly AccountSecurityService $accounts,
        private readonly EmailChangeService $emails,
        private readonly StepUpService $stepUp,
    ) {}

    // change the authenticated user's password (requires the current password)
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->accounts->changePassword(
            $request->user(),
            $validated['current_password'],
            $validated['password'],
            $request,
        );

        return response()->json([
            'message' => 'Your password has been changed. Please sign in again with your new password.',
        ]);
    }

    // start a two-stage email change (a code is sent to the new address)
    public function requestEmailChange(RequestEmailChangeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $change = $this->emails->request(
            $request->user(),
            $validated['new_email'],
            $validated['password'],
            $request,
        );

        return response()->json([
            'message' => 'A confirmation code has been sent to the new email address.',
            'new_email' => $change->new_email,
            'expires_in' => (int) config('auth.email_change.expire_minutes', 15) * 60,
        ]);
    }

    // complete the email change by confirming the code sent to the new address
    public function confirmEmailChange(ConfirmEmailChangeRequest $request): JsonResponse
    {
        $user = $this->emails->confirm($request->user(), $request->validated()['code'], $request);

        return response()->json([
            'message' => 'Your email address has been updated. Please sign in again.',
            'email' => $user->email,
        ]);
    }

    // obtain a single-use step-up grant for a sensitive action
    public function stepUp(StepUpRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->stepUp->authorize(
            $request->user(),
            $validated['action'],
            $validated['password'],
            $request,
        );

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json([
            'message' => 'Identity confirmed.',
            'step_up_token' => $result['token'],
            'action' => $validated['action'],
            'expires_in' => $result['expires_in'],
        ]);
    }

    // list the authenticated user's recent security events
    public function securityEvents(Request $request): JsonResponse
    {
        $events = $request->user()->securityEvents()
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn ($event) => [
                'id' => $event->id,
                'event' => $event->event,
                'severity' => $event->severity,
                'ip_address' => $event->ip_address,
                'context' => $event->context,
                'created_at' => $event->created_at,
            ]);

        return response()->json(['data' => $events]);
    }
}
