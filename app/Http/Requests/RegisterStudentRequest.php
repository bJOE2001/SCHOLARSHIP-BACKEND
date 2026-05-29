<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fullName' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'string', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', $this->passwordRule()],
            'gender' => ['nullable', 'string', 'max:50'],
            'birthDate' => ['nullable', 'date'],
            'civilStatus' => ['nullable', 'string', 'max:50'],
            'citizenship' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'contactNumber' => ['nullable', 'string', 'max:30'],
            'schoolName' => ['nullable', 'string', 'max:255'],
            'familyIncome' => ['nullable', 'numeric', 'min:0'],
            'studentId' => ['nullable', 'string', 'max:100'],
            'course' => ['nullable', 'string', 'max:255'],
            'yearLevel' => ['nullable', 'string', 'max:50'],
            'semester' => ['nullable', Rule::in(['1st Semester', '2nd Semester'])],
            'academicYear' => ['nullable', 'string', 'max:50'],
            'gpa' => ['nullable', 'numeric', 'min:1', 'max:100'],
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

    /**
     * Get the password policy for student registration.
     */
    private function passwordRule(): Password
    {
        $passwordRule = Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols();

        return app()->isProduction()
            ? $passwordRule->uncompromised()
            : $passwordRule;
    }
}
