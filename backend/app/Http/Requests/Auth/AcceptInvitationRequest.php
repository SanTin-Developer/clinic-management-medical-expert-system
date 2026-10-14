<?php

namespace App\Http\Requests\Auth;

use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class AcceptInvitationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'nullable',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9._-]+$/',
                function ($attribute, $value, $fail) {
                    $token = (string) $this->input('token');
                    $invitation = StaffInvitation::where('token_hash', hash('sha256', $token))->first();
                    $ignoreId = $invitation?->user_id;
                    if (User::where('username', strtolower($value))->where('id', '!=', $ignoreId)->exists()) {
                        $fail('The username has already been taken.');
                    }
                },
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                Rules\Password::min((int) config('auth.password_min_length', 8))
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }
}
