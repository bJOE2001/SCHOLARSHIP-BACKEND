<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SemesterRequirementDraftResource extends JsonResource
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
            'scholarId' => $this->scholar_id,
            'scholarshipApplicationId' => $this->scholarship_application_id,
            'applicationId' => $this->scholarship_application_id,
            'status' => $this->status,
            'grades' => $this->grades ?? [],
            'gradeRows' => $this->grades ?? [],
            'encodedGrades' => $this->grades ?? [],
            'computedAverage' => $this->computed_average,
            'submittedAt' => $this->submitted_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
