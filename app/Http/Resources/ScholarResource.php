<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScholarResource extends JsonResource
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
            'userId' => $this->user_id,
            'scholarshipProgramId' => $this->scholarship_program_id,
            'scholarshipApplicationId' => $this->scholarship_application_id,
            'scholarId' => $this->scholar_id,
            'name' => $this->name,
            'avatar' => $this->avatar,
            'program' => $this->program,
            'course' => $this->course,
            'yearLevel' => $this->year_level,
            'school' => $this->school,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate?->toDateString(),
            'address' => $this->address,
            'contactNumber' => $this->contact_number,
            'email' => $this->email,
            'gpa' => $this->gpa,
            'enrollmentStatus' => $this->enrollment_status,
            'academicYear' => $this->academic_year,
            'semester' => $this->semester,
            'scholarshipStatus' => $this->scholarship_status,
            'renewalStatus' => $this->renewal_status,
            'dateApproved' => $this->date_approved?->toDateString(),
            'duration' => $this->duration,
            'complianceStatus' => $this->compliance_status,
            'complianceRate' => $this->compliance_rate,
            'riskLevel' => $this->risk_label,
            'riskLabel' => $this->risk_label,
            'riskReason' => $this->risk_reason,
            'riskRemarks' => $this->risk_reason,
            'officerNotes' => $this->recommended_action,
            'recommendedAction' => $this->recommended_action,
            'submissions' => $this->submissions ?? [],
            'complianceHistory' => [
                [
                    'semester' => $this->semester,
                    'status' => $this->compliance_status,
                    'remarks' => $this->risk_reason,
                ],
            ],
            'renewalHistory' => [
                [
                    'semester' => $this->semester,
                    'status' => $this->renewal_status,
                    'remarks' => $this->recommended_action,
                ],
            ],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
