<?php

namespace App\Http\Requests\Staff;

use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadStaffDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof Staff && (
            $this->user()->can('staff.document.upload') ||
            $this->user()->can('staff.update')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(StaffDocument::CATEGORIES)],
            'file' => ['required', 'file', 'max:'.(int) config('documents.max_size_kb', 10240)],
        ];
    }
}
