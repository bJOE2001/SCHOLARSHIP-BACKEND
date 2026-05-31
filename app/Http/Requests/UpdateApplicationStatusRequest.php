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
                'Resubmitted',
                'Under Review',
                'Application Review Approved',
                'For Revision',
                'Needs Revision',
                'Eligible',
                'Shortlisted',
                'Approved',
                'Accepted',
                'Rejected',
                'Enrollment Verified',
                'Active Scholar',
                'Pending Renewal',
                'Under Renewal Review',
                'Probation',
                'Suspended',
                'Renewal Pending',
                'Renewed',
                'Terminated',
            ])],
            'remarks' => ['required_if:status,Rejected', 'nullable', 'string'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'remarks.required_if' => 'Rejection remarks are required.',
        ];
    }
}
