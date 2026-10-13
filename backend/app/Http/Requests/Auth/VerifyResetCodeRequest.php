<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyResetCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'digits:'.(int) config('auth.password_reset.code_length', 6)],
        ];
    }
}
