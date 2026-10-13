<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\SuspendUserRequest;
use App\Models\User;
use App\Services\Auth\AccountSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function __construct(
        private readonly AccountSecurityService $accounts,
    ) {}

    // Display a paginated list of users
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('roles:id,name')
            ->latest()
            ->paginate(15);

        return response()->json($users);
    }

    // Store a new user
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', $this->passwordRule()],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name'),
            ],
        ]);

        $username = ! empty($validatedData['username'])
            ? strtolower($validatedData['username'])
            : $this->generateUsername($validatedData['email']);

        $user = User::create([
            'name' => $validatedData['name'],
            'username' => $username,
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
        ]);

        $user->assignRole($validatedData['role']);

        return response()->json([
            'message' => 'User created successfully.',
            'user' => $user,
        ], 201);
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
            'password' => ['sometimes', 'required', 'string', $this->passwordRule()],
            'role' => [
                'sometimes',
                'required',
                'string',
                Rule::exists('roles', 'name'),
            ],
        ]);

        $user->update([
            'name' => $validated['name'] ?? $user->name,
            'username' => ! empty($validated['username']) ? strtolower($validated['username']) : $user->username,
            'email' => $validated['email'] ?? $user->email,
        ]);

        if (! empty($validated['password'])) {
            $user->update([
                'password' => Hash::make($validated['password']),
            ]);
        }

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
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

    // Shared password complexity rule (8+, upper, lower, number, symbol)
    private function passwordRule(): Rules\Password
    {
        return Rules\Password::min((int) config('auth.password_min_length', 8))
            ->mixedCase()
            ->numbers()
            ->symbols();
    }

    // Derive a unique username from an email address local part
    private function generateUsername(string $email): string
    {
        $base = strstr($email, '@', true) ?: $email;
        $base = strtolower($base);
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?? '';
        $base = trim($base, '._-') ?: 'user';

        $candidate = $base;
        $suffix = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
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
