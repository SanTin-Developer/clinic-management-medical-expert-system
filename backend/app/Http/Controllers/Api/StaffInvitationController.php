<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreInvitationRequest;
use App\Models\StaffInvitation;
use App\Models\User;
use App\Services\Auth\StaffProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffInvitationController extends Controller
{
    public function __construct(
        private readonly StaffProvisioningService $provisioning,
    ) {}

    // list outstanding and historical staff invitations
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $invitations = StaffInvitation::query()
            ->with(['inviter:id,name'])
            ->latest()
            ->paginate(15);

        return response()->json($invitations);
    }

    // invite a new staff member
    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $this->authorize('invite', User::class);

        $result = $this->provisioning->invite($request->user(), $request->validated(), $request);

        $invitation = $result['invitation'];

        return response()->json([
            'message' => 'Invitation sent.',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'name' => $invitation->name,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
                'accepted_at' => $invitation->accepted_at,
                'revoked_at' => $invitation->revoked_at,
            ],
        ], 201);
    }

    // re-send an invitation email with a fresh token
    public function resend(Request $request, StaffInvitation $invitation): JsonResponse
    {
        $this->authorize('invite', User::class);

        $result = $this->provisioning->resend($invitation, $request);

        return response()->json([
            'message' => 'Invitation re-sent.',
            'invitation' => [
                'id' => $result['invitation']->id,
                'email' => $result['invitation']->email,
                'expires_at' => $result['invitation']->expires_at,
            ],
        ]);
    }

    // revoke an outstanding invitation
    public function destroy(Request $request, StaffInvitation $invitation): JsonResponse
    {
        $this->authorize('invite', User::class);

        $this->provisioning->revoke($invitation, $request);

        return response()->json(['message' => 'Invitation revoked.']);
    }
}
