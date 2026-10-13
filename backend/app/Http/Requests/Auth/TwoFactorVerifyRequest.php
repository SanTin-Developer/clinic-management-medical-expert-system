<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorVerifyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string', 'max:20'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => trim((string) $this->input('code'))]);
        }
    }
}
