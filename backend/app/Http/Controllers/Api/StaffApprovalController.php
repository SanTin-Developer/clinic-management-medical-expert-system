<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\RejectRegistrationRequest;
use App\Models\User;
use App\Services\Auth\AccountSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Administrator review queue for staff accounts that have accepted an
 * invitation and are awaiting approval. Approving or rejecting is gated by the
 * `users.approve` permission and (for reject) server-side step-up.
 */
class StaffApprovalController extends Controller
{
    public function __construct(
        private readonly AccountSecurityService $accounts,
    ) {}

    /**
     * List accounts currently awaiting approval, newest registration first.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('approve', User::class);

        $query = User::query()
            ->where('account_status', User::STATUS_PENDING_APPROVAL)
            ->with(['roles:id,name', 'staffInvitations' => fn ($query) => $query->latest()->limit(1)]);
        $base = clone $query;

        $pending = $query->latest()->paginate(15);

        $stats = [
            'total' => $pending->total(),
            'this_month' => (clone $base)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json(array_merge($pending->toArray(), ['stats' => $stats]));
    }

    /**
     * Approve a pending registration, making the account eligible to sign in.
     */
    public function approve(Request $request, User $user): JsonResponse
    {
        $this->authorize('approve', User::class);

        $this->accounts->approve($request->user(), $user, $request);

        return response()->json([
            'message' => 'Account approved. The staff member can now sign in.',
            'user' => $this->userData($user->fresh()),
        ]);
    }

    /**
     * Reject a pending registration. A reason is required and the account is
     * blocked from authenticating.
     */
    public function reject(RejectRegistrationRequest $request, User $user): JsonResponse
    {
        $this->authorize('approve', User::class);

        $this->accounts->reject($request->user(), $user, $request->validated()['reason'], $request);

        return response()->json([
            'message' => 'Account rejected.',
            'user' => $this->userData($user->fresh()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'staff_id' => $user->staff_id,
            'phone' => $user->phone,
            'account_status' => $user->account_status,
            'approved_at' => $user->approved_at,
            'approved_by' => $user->approved_by,
            'rejected_at' => $user->rejected_at,
            'rejected_by' => $user->rejected_by,
            'rejection_reason' => $user->rejection_reason,
            'roles' => $user->getRoleNames(),
        ];
    }
}
