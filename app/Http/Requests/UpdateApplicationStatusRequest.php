<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateApplicationStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                'Draft',
                'Submitted',
                'Under Review',
                'Needs Revision',
                'Accepted',
                'Rejected',
                'Enrollment Verified',
                'Active Scholar',
                'Renewal Pending',
                'Renewed',
                'Terminated',
            ])],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
