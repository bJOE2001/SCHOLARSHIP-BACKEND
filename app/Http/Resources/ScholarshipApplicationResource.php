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
            'applicationNo' => $this->application_no,
            'status' => $this->status,
            'riskLabel' => $this->risk_label,
            'score' => $this->score,
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
}
