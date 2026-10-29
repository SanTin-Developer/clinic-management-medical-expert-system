<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserSettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        // Usernames are stored lowercase; login lowercases the identifier before
        // querying, so accepting mixed case here would lock the user out.
        if ($request->filled('username')) {
            $request->merge(['username' => strtolower(trim((string) $request->input('username')))]);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['nullable', 'string', 'timezone:all'],
            'locale' => ['nullable', 'string', 'max:10', Rule::in(['en', 'km'])],
        ]);

        $user->fill($validated);
        $user->save();

        return response()->json([
            'message' => 'Profile updated.',
            'data' => [
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'name' => $user->name,
                'username' => $user->username,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_path ? asset('storage/'.$user->avatar_path) : null,
            ],
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120'],
        ]);

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar_path = $path;
        $user->save();

        return response()->json([
            'message' => 'Profile picture updated.',
            'data' => [
                'avatar_url' => asset('storage/'.$path),
            ],
        ]);
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        return response()->json([
            'message' => 'Profile picture removed.',
            'data' => [
                'avatar_url' => null,
            ],
        ]);
    }

    public function exportData(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing(['roles', 'permissions']);

        $data = [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'last_login_at' => $user->last_login_at,
            ],
            'roles' => $user->roles->map(fn ($role) => ['name' => $role->name]),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];

        return response()->json([
            'message' => 'Data export ready.',
            'data' => $data,
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'confirmation' => ['required', 'string'],
        ]);

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        if (strtolower(trim($validated['confirmation'])) !== 'delete') {
            throw ValidationException::withMessages([
                'confirmation' => ['Type DELETE to confirm account deletion.'],
            ]);
        }

        // Revoke tokens
        $user->tokens()->delete();

        // Best-effort cleanup of trusted devices
        $user->trustedDevices()->delete();

        $user->delete();

        return response()->json([
            'message' => 'Your account has been deleted.',
        ]);
    }
}
