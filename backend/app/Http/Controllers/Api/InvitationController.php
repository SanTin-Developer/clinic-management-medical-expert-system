<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Requests\Auth\LookupInvitationRequest;
use App\Services\Auth\StaffProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Public endpoints used by the invitation acceptance screen. Only a hash of
 * the invitation token is ever stored, and the token is never echoed back.
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly StaffProvisioningService $provisioning,
    ) {}

    // look up an invitation so the UI can show who it is for
    public function lookup(LookupInvitationRequest $request): JsonResponse
    {
        $invitation = $this->provisioning->findByToken($request->validated()['token']);

        if ($invitation === null || ! $invitation->isPending()) {
            throw ValidationException::withMessages([
                'token' => ['This invitation is invalid, expired or has already been used.'],
            ]);
        }

        return response()->json([
            'name' => $invitation->name,
            'email' => $this->maskEmail($invitation->email),
            'role' => $invitation->role,
            'expires_at' => $invitation->expires_at,
        ]);
    }

    // accept the invitation, set a password and activate the account
    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->provisioning->accept($validated['token'], $validated, $request);

        return response()->json([
            'message' => 'Your account has been activated. Sign in and enable two-factor authentication to continue.',
            'email' => $user->email,
            'username' => $user->username,
        ]);
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        if ($domain === '') {
            return '***';
        }

        $visible = substr($local, 0, 1);

        return $visible.str_repeat('*', max(1, strlen($local) - 1)).'@'.$domain;
    }
}
