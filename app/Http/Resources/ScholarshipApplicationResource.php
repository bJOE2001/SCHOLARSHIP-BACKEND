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
            'timeline' => $this->timelineForResponse(),
            'submittedAt' => $this->submitted_at?->toISOString(),
            'reviewedAt' => $this->reviewed_at?->toISOString(),
            'reviewedById' => $this->reviewed_by_id,
            'reviewedByName' => $this->whenLoaded('reviewer', fn (): ?string => $this->reviewer?->name),
            'approvedByName' => $this->whenLoaded('reviewer', fn (): ?string => $this->reviewer?->name),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Return timeline entries with a real timestamp when a review time exists.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timelineForResponse(): array
    {
        return collect($this->timeline ?? [])
            ->map(function (array $event): array {
                if (
                    $this->reviewed_at !== null
                    && ($event['status'] ?? null) === $this->status
                    && $this->isDateOnlyTimelineValue($event['date'] ?? null)
                ) {
                    $event['date'] = $this->reviewed_at->toISOString();
                }

                return $event;
            })
            ->values()
            ->all();
    }

    private function isDateOnlyTimelineValue(mixed $date): bool
    {
        return is_string($date)
            && ! str_contains($date, 'T')
            && ! preg_match('/\d{1,2}:\d{2}/', $date);
    }

    private function eligibilityForStatus(): string
    {
        return match ($this->status) {
            'Under Review' => 'For Evaluation',
            'Application Review Approved' => 'Pending Validation',
            'Eligible', 'Shortlisted', 'Approved', 'Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewed' => 'Eligible',
            'Rejected', 'Terminated' => 'Ineligible',
            default => 'For Screening',
        };
    }

    private function recommendationForStatus(): string
    {
        return match ($this->status) {
            'Submitted', 'Under Review', 'Application Review Approved', 'For Revision', 'Resubmitted' => 'Pending',
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
