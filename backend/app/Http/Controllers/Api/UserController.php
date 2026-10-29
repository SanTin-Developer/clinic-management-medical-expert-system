<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\SuspendUserRequest;
use App\Models\User;
use App\Services\Auth\AccountSecurityService;
use App\Services\Auth\SecurityEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private readonly AccountSecurityService $accounts,
        private readonly SecurityEventService $events,
    ) {}

    // Display a paginated list of users
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with('roles:id,name');
        $base = clone $query;

        if ($search = $request->query('search')) {
            $query->where(function ($builder) use ($search) {
                foreach (['name', 'username', 'email'] as $column) {
                    $builder->orWhereLike($column, "%{$search}%", caseSensitive: false);
                }
            });
        }

        $users = $query->latest()->paginate(15);

        $stats = [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('account_status', 'active')->count(),
            'inactive' => (clone $base)->where('account_status', 'inactive')->count(),
            'suspended' => (clone $base)->where('account_status', 'suspended')->count(),
            'locked' => (clone $base)->where('account_status', 'locked')->count(),
            'unverified' => (clone $base)->whereNull('email_verified_at')->count(),
            'verified' => (clone $base)->whereNotNull('email_verified_at')->count(),
            'this_month' => (clone $base)->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
        ];

        return response()->json(array_merge($users->toArray(), ['stats' => $stats]));
    }

    // Store a new user
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $this->rejectPasswordManagementInput($request, null);

        throw ValidationException::withMessages([
            'account' => ['Staff accounts must be provisioned through the invitation workflow.'],
        ]);
    }

    // Display a specific user
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load('roles:id,name');

        return response()->json([
            'user' => $this->userData($user),
        ]);
    }

    // Update an existing user
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $this->rejectPasswordManagementInput($request, $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::exists('roles', 'name'),
            ],
        ]);

        if (isset($validated['role']) && ! $request->user()->hasRole('Admin')) {
            throw ValidationException::withMessages([
                'role' => ['You are not authorized to assign roles.'],
            ]);
        }

        $user->update([
            'name' => $validated['name'] ?? $user->name,
            'username' => ! empty($validated['username']) ? strtolower($validated['username']) : $user->username,
            'email' => $validated['email'] ?? $user->email,
        ]);

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);

            $this->events->record(SecurityEventService::USER_ROLE_CHANGED, $user, 'info', [
                'actor_id' => $request->user()->id,
                'role' => $validated['role'],
            ], $request);
        }

        return response()->json([
            'message' => 'User updated successfully.',
            'user' => $this->userData($user->fresh()),
        ]);
    }

    // Delete a user
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    // Suspend an account and revoke its sessions/devices
    public function suspend(SuspendUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('suspend', User::class);

        $this->accounts->suspend($user, $request->validated()['reason'] ?? null, $request);

        return response()->json([
            'message' => 'User suspended successfully.',
            'user' => $this->userData($user->fresh()),
        ]);
    }

    // Reactivate a suspended or pending account
    public function activate(Request $request, User $user): JsonResponse
    {
        $this->authorize('suspend', User::class);

        $this->accounts->activate($user, $request);

        return response()->json([
            'message' => 'User activated successfully.',
            'user' => $this->userData($user->fresh()),
        ]);
    }

    // Disable an account and revoke its sessions/devices
    public function disable(Request $request, User $user): JsonResponse
    {
        $this->authorize('suspend', User::class);

        $this->accounts->disable($user, $request);

        return response()->json([
            'message' => 'User disabled successfully.',
            'user' => $this->userData($user->fresh()),
        ]);
    }

    private function rejectPasswordManagementInput(Request $request, ?User $target): void
    {
        if (! $request->hasAny(['password', 'password_confirmation', 'current_password'])) {
            return;
        }

        $actor = $request->user();

        $this->events->record(SecurityEventService::USER_PASSWORD_CHANGE_REJECTED, $target ?? $actor, 'warning', [
            'actor_id' => $actor?->id,
            'target_id' => $target?->id,
        ], $request);

        throw ValidationException::withMessages([
            'password' => ['Passwords cannot be set through user management. Use the password-reset process.'],
        ]);
    }

    // Format user data for response
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
            'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            'last_login_at' => $user->last_login_at,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
