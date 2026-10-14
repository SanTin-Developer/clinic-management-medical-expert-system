<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class StepUpRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9._-]+$/'],
            'password' => ['required', 'string'],
            // Optional second factor; only required when the account has TOTP
            // enrolled and step-up two-factor enforcement is enabled.
            'two_factor_code' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
