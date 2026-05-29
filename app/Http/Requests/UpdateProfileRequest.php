<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'string',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'avatar' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'studentId' => ['nullable', 'string', 'max:100'],
            'birthDate' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],
            'civilStatus' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'contactNumber' => ['nullable', 'string', 'max:30'],
            'campus' => ['nullable', 'string', 'max:255'],
            'schoolName' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'yearLevel' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', Rule::in(['1st Semester', '2nd Semester'])],
            'academicYear' => ['nullable', 'string', 'max:50'],
            'gpa' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'familyIncome' => ['nullable', 'numeric', 'min:0'],
            'enrollmentStatus' => ['nullable', 'string', 'max:100'],
            'academicAwards' => ['nullable', 'string'],
            'fatherName' => ['nullable', 'string', 'max:255'],
            'motherName' => ['nullable', 'string', 'max:255'],
            'guardianName' => ['nullable', 'string', 'max:255'],
            'parentOccupation' => ['nullable', 'string', 'max:255'],
            'monthlyIncome' => ['nullable', 'string', 'max:100'],
            'siblings' => ['nullable', 'integer', 'min:0'],
            'studyingSiblings' => ['nullable', 'integer', 'min:0'],
            'incomeBracket' => ['nullable', 'string', 'max:100'],
        ];
    }
}
