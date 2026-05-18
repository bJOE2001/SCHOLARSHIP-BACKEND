<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScholarComplianceRequest extends FormRequest
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
            'complianceStatus' => ['required', Rule::in(['Compliant', 'Incomplete', 'Late Submission', 'Non-Compliant', 'Complete', 'Pending Review', 'Pending Compliance', 'Missing Requirements', 'Under Review'])],
            'riskLevel' => ['nullable', Rule::in(['Low Risk', 'Medium Risk', 'High Risk', 'Stable', 'Borderline', 'At Risk', 'Critical'])],
            'scholarshipStatus' => ['nullable', Rule::in(['Active Scholar', 'Pending Renewal', 'Under Renewal Review', 'Probation', 'Suspended', 'Active'])],
            'renewalStatus' => ['nullable', Rule::in(['Active Scholar', 'Pending Renewal', 'Pending Review', 'Under Review', 'Under Renewal Review', 'Approved', 'Probation', 'Suspended', 'Active', 'Renewal Pending', 'Under Evaluation'])],
            'renewalEligibility' => ['nullable', 'string', 'max:255'],
            'officerNotes' => ['nullable', 'string'],
            'recommendedAction' => ['nullable', 'string'],
            'coeStatus' => ['nullable', 'string', 'max:255'],
            'corStatus' => ['nullable', 'string', 'max:255'],
            'gradesStatus' => ['nullable', 'string', 'max:255'],
        ];
    }
}
