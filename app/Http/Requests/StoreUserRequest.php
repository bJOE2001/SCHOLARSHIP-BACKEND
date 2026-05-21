<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'email' => ['required', 'email', 'string', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(['student', 'head_officer', 'officer'])],
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'department' => ['nullable', 'string', 'max:255'],
            'studentId' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'schoolName' => ['nullable', 'string', 'max:255'],
            'familyIncome' => ['nullable', 'numeric', 'min:0'],
            'assignedProgramIds' => ['nullable', 'array'],
            'assignedProgramIds.*' => ['integer', 'exists:scholarship_programs,id'],
            'programIds' => ['nullable', 'array'],
            'programIds.*' => ['integer', 'exists:scholarship_programs,id'],
        ];
    }
}
