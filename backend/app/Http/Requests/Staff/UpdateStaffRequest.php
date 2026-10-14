<?php

namespace App\Http\Requests\Staff;

use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof Staff && $this->user()->can('update', $staff);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $staffId = $this->route('staff')?->id;

        return [
            'staff_number' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('staff', 'staff_number')->ignore($staffId),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'gender' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => [
                'nullable',
                'string',
                Rule::in([
                    Staff::EMPLOYMENT_TYPE_FULL_TIME,
                    Staff::EMPLOYMENT_TYPE_PART_TIME,
                    Staff::EMPLOYMENT_TYPE_CONTRACT,
                    Staff::EMPLOYMENT_TYPE_INTERN,
                ]),
            ],
            'employment_status' => [
                'nullable',
                'string',
                Rule::in([
                    Staff::STATUS_EMPLOYED,
                    Staff::STATUS_ON_LEAVE,
                    Staff::STATUS_RESIGNED,
                    Staff::STATUS_TERMINATED,
                    Staff::STATUS_PROBATION,
                ]),
            ],
            'hire_date' => ['nullable', 'date'],
            'work_schedule' => ['nullable', 'string', 'max:255'],
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists('staff', 'id'),
                Rule::notIn([$staffId]),
            ],
            'education' => ['nullable', 'string', 'max:255'],
            'qualifications' => ['nullable', 'string', 'max:2000'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'license_expiry' => ['nullable', 'date'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'pay_frequency' => ['nullable', 'string', Rule::in(['monthly', 'biweekly', 'hourly'])],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'salary_note' => ['nullable', 'string', 'max:255'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('staff', 'user_id')->ignore($staffId),
            ],
        ];
    }
}
