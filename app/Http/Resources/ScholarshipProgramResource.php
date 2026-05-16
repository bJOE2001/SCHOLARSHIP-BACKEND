<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScholarshipProgramResource extends JsonResource
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
            'provider' => $this->provider,
            'category' => $this->category,
            'type' => $this->type,
            'description' => $this->description,
            'eligibilitySummary' => $this->eligibility_summary,
            'status' => $this->status,
            'slots' => $this->slots,
            'usedSlots' => $this->used_slots,
            'availableSlots' => $this->availableSlots(),
            'budget' => $this->budget,
            'schedule' => $this->schedule ?? [],
            'eligibility' => $this->eligibility ?? [],
            'requirements' => $this->requirements ?? [],
            'scoringCriteria' => $this->scoring_criteria ?? [],
            'renewalRules' => $this->renewal_rules ?? [],
            'assignedOfficerIds' => $this->assigned_admin_ids ?? [],
            'publishedAt' => $this->published_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
