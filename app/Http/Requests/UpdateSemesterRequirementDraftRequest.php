<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSemesterRequirementDraftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scholarId' => ['nullable', 'integer', 'exists:scholars,id'],
            'applicationId' => ['nullable', 'integer', 'exists:scholarship_applications,id'],
            'scholarshipApplicationId' => ['nullable', 'integer', 'exists:scholarship_applications,id'],
            'status' => ['nullable', 'string', Rule::in(['Draft', 'Submitted'])],
            'grades' => ['nullable', 'array'],
            'grades.*.id' => ['nullable'],
            'grades.*.code' => ['nullable', 'string', 'max:255'],
            'grades.*.subjectCode' => ['nullable', 'string', 'max:255'],
            'grades.*.name' => ['nullable', 'string', 'max:255'],
            'grades.*.subjectName' => ['nullable', 'string', 'max:255'],
            'grades.*.units' => ['nullable', 'numeric', 'min:0'],
            'grades.*.grade' => ['nullable', 'numeric', 'min:0'],
            'computedAverage' => ['nullable', 'numeric', 'min:0'],
            'submittedAt' => ['nullable', 'date'],
        ];
    }
}
