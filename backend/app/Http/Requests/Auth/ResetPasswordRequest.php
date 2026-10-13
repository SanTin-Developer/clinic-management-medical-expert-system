<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class ResetPasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
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