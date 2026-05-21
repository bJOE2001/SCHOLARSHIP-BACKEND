<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScholarshipApplicationResource extends JsonResource
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
            'scholarshipProgramId' => $this->scholarship_program_id,
            'applicantId' => $this->applicant_id,
            'applicantName' => $this->whenLoaded('applicant', fn (): ?string => $this->applicant?->name),
            'applicantGpa' => $this->whenLoaded('applicant', fn (): ?float => $this->applicant?->gpa),
            'applicant' => new UserResource($this->whenLoaded('applicant')),
            'programName' => $this->whenLoaded('program', fn (): ?string => $this->program?->name),
            'applicationNo' => $this->application_no,
            'status' => $this->status,
            'eligibility' => $this->eligibilityForStatus(),
            'recommendation' => $this->recommendationForStatus(),
            'riskLabel' => $this->risk_label,
            'score' => $this->scoreForStatus($this->status, (int) $this->score),
            'progress' => $this->progress,
            'remarks' => $this->remarks,
            'nextAction' => $this->next_action,
            'missingRequirements' => $this->missing_requirements ?? [],
            'timeline' => $this->timeline ?? [],
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'reviewedById' => $this->reviewed_by_id,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    private function eligibilityForStatus(): string
    {
        return match ($this->status) {
            'Under Review' => 'For Evaluation',
            'Eligible', 'Shortlisted', 'Approved', 'Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewed' => 'Eligible',
            'Rejected', 'Terminated' => 'Ineligible',
            default => 'For Screening',
        };
    }

    private function recommendationForStatus(): string
    {
        return match ($this->status) {
            'Submitted', 'Under Review', 'For Revision', 'Resubmitted' => 'Pending',
            'Eligible', 'Shortlisted', 'Approved', 'Active Scholar' => 'Recommended',
            'Rejected' => 'Not Recommended',
            default => 'Pending',
        };
    }

    private function scoreForStatus(string $status, int $currentScore): int
    {
        return match ($status) {
            'Eligible' => max($currentScore, 80),
            'Shortlisted', 'Approved', 'Active Scholar' => max($currentScore, 90),
            default => $currentScore,
        };
    }
}
