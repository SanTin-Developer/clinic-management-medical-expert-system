<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;

class ChangePasswordRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'different:current_password',
                Rules\Password::min((int) config('auth.password_min_length', 8))
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }
}
