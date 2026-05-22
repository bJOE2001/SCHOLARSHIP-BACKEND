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
        $complianceSubmissions = $this->whenLoaded('complianceSubmissions', fn () => $this->complianceSubmissions, collect());
        $latestComplianceSubmission = $complianceSubmissions->first();
        $submissions = $latestComplianceSubmission?->submissions ?? $this->submissions ?? [];
        $encodedGradesSubmission = $this->submissionFor($submissions, 'encoded-grades');
        $encodedGrades = $latestComplianceSubmission?->grades
            ?? $encodedGradesSubmission['grades']
            ?? $encodedGradesSubmission['gradeRows']
            ?? $encodedGradesSubmission['encodedGrades']
            ?? [];

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
            'submissions' => $submissions,
            'coeStatus' => $this->submissionStatus($submissions, 'coe'),
            'corStatus' => $this->submissionStatus($submissions, 'cor'),
            'gradesStatus' => $this->submissionStatus($submissions, 'encoded-grades'),
            'grades' => $encodedGrades,
            'gradeRows' => $encodedGrades,
            'encodedGrades' => $encodedGrades,
            'semesterSubmissionStatus' => $latestComplianceSubmission?->status ?? $encodedGradesSubmission['status'] ?? null,
            'latestComplianceSubmission' => $latestComplianceSubmission ? $this->complianceSubmissionPayload($latestComplianceSubmission) : null,
            'complianceHistory' => $complianceSubmissions->isNotEmpty()
                ? $complianceSubmissions->map(fn ($submission): array => $this->complianceSubmissionPayload($submission))->values()->all()
                : [
                    [
                        'semester' => $this->semester,
                        'status' => $this->compliance_status,
                        'remarks' => $this->risk_reason,
                        'date' => $this->updated_at?->toISOString(),
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

    /**
     * Build frontend payload for one compliance submission row.
     *
     * @return array<string, mixed>
     */
    private function complianceSubmissionPayload($submission): array
    {
        return [
            'id' => $submission->id,
            'semester' => $submission->semester,
            'academicYear' => $submission->academic_year,
            'status' => $submission->status,
            'coeStatus' => $submission->coe_status,
            'corStatus' => $submission->cor_status,
            'gradesStatus' => $submission->grades_status,
            'gpa' => $submission->gpa,
            'submissions' => $submission->submissions ?? [],
            'grades' => $submission->grades ?? [],
            'gradeRows' => $submission->grades ?? [],
            'encodedGrades' => $submission->grades ?? [],
            'remarks' => $submission->officer_notes,
            'officerNotes' => $submission->officer_notes,
            'submittedAt' => $submission->submitted_at?->toISOString(),
            'reviewedAt' => $submission->reviewed_at?->toISOString(),
            'date' => ($submission->reviewed_at ?? $submission->submitted_at ?? $submission->created_at)?->toISOString(),
            'createdAt' => $submission->created_at?->toISOString(),
            'updatedAt' => $submission->updated_at?->toISOString(),
        ];
    }
    /**
     * Find a saved semester submission entry by stable key.
     *
     * @param array<int, array<string, mixed>> $submissions
     * @return array<string, mixed>
     */
    private function submissionFor(array $submissions, string $key): array
    {
        foreach ($submissions as $submission) {
            $submissionKey = $submission['key'] ?? str($submission['requirement'] ?? $submission['name'] ?? '')->lower()->slug('-')->toString();

            if ($submissionKey === $key) {
                if (in_array($key, ['coe', 'cor'], true) && isset($submission['document']) && ! isset($submission['requestedAt'])) {
                    continue;
                }

                return $submission;
            }
        }

        return [];
    }

    /**
     * Return the saved status for one semester requirement.
     *
     * @param array<int, array<string, mixed>> $submissions
     */
    private function submissionStatus(array $submissions, string $key): ?string
    {
        return $this->submissionFor($submissions, $key)['status'] ?? null;
    }
}
