<?php

namespace App\Http\Requests;

use App\Models\SystemAlert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemAlertIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SystemAlert::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(['active', 'unread', 'read', 'acknowledged', 'resolved'])],
            'severity' => ['nullable', Rule::in(SystemAlert::SEVERITIES)],
            'category' => ['nullable', Rule::in(SystemAlert::CATEGORIES)],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
