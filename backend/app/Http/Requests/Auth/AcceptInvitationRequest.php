<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
                Rule::unique('users', 'username'),
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
