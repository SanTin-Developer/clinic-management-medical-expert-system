<?php

namespace App\Http\Requests\Staff;

use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffSalaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof Staff && $this->user()->can('updateSalary', $staff);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'salary_amount' => ['required', 'numeric', 'min:0'],
            'pay_frequency' => ['required', 'string', Rule::in(['monthly', 'biweekly', 'hourly'])],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
