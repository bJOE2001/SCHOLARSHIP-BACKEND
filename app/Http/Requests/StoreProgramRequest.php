<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgramRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'eligibilitySummary' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['Open', 'Closing Soon', 'Closed', 'Archived'])],
            'slots' => ['nullable', 'integer', 'min:0'],
            'usedSlots' => ['nullable', 'integer', 'min:0'],
            'budget' => ['nullable', 'integer', 'min:0'],
            'schedule' => ['nullable', 'array'],
            'schedule.opening' => ['nullable', 'string', 'max:100'],
            'schedule.deadline' => ['nullable', 'string', 'max:100'],
            'schedule.screening' => ['nullable', 'string', 'max:100'],
            'schedule.awarding' => ['nullable', 'string', 'max:100'],
            'eligibility' => ['nullable', 'array'],
            'eligibility.*' => ['string', 'max:255'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string', 'max:255'],
            'scoringCriteria' => ['nullable', 'array'],
            'scoringCriteria.*' => ['string', 'max:255'],
            'renewalRules' => ['nullable', 'array'],
            'renewalRules.*' => ['string', 'max:255'],
            'assignedOfficerIds' => ['nullable', 'array'],
            'assignedOfficerIds.*' => ['integer', Rule::exists('users', 'id')],
            'assignedAdminIds' => ['nullable', 'array'],
            'assignedAdminIds.*' => ['integer', Rule::exists('users', 'id')],
        ];
    }
}
