<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'forcePasswordChange' => (bool) $this->force_password_change,
            'avatar' => $this->avatar,
            'department' => $this->department,
            'studentId' => $this->student_id,
            'birthDate' => $this->birth_date?->toDateString(),
            'gender' => $this->gender,
            'civilStatus' => $this->civil_status,
            'citizenship' => $this->citizenship,
            'address' => $this->address,
            'barangay' => $this->barangay,
            'city' => $this->city,
            'province' => $this->province,
            'contactNumber' => $this->contact_number,
            'campus' => $this->campus,
            'schoolName' => $this->school_name,
            'course' => $this->course,
            'yearLevel' => $this->year_level,
            'semester' => $this->semester,
            'academicYear' => $this->academic_year,
            'gpa' => $this->gpa,
            'familyIncome' => $this->family_income,
            'annualFamilyIncome' => $this->family_income,
            'enrollmentStatus' => $this->enrollment_status,
            'academicAwards' => $this->academic_awards,
            'fatherName' => $this->father_name,
            'motherName' => $this->mother_name,
            'guardianName' => $this->guardian_name,
            'parentOccupation' => $this->parent_occupation,
            'monthlyIncome' => $this->monthly_income,
            'siblings' => $this->siblings,
            'studyingSiblings' => $this->studying_siblings,
            'incomeBracket' => $this->income_bracket,
            'assignedProgramIds' => $this->assigned_program_ids ?? [],
            'assignedPrograms' => $this->assigned_program_ids ?? [],
            'emailVerifiedAt' => $this->email_verified_at?->toISOString(),
            'birthdate' => $this->birth_date?->toDateString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
