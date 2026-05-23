<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $program = $this->relationLoaded('program') ? $this->program : null;
        $createdBy = $this->relationLoaded('createdBy') ? $this->createdBy : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'programId' => $this->scholarship_program_id,
            'programName' => $program?->name,
            'semester' => $this->semester,
            'schoolYear' => $this->school_year,
            'amount' => $this->amount,
            'claimingStartDate' => $this->claiming_start_date?->toDateString(),
            'claimingEndDate' => $this->claiming_end_date?->toDateString(),
            'venue' => $this->venue,
            'dailyLimit' => $this->daily_limit,
            'remarks' => $this->remarks,
            'status' => $this->status,
            'createdBy' => $createdBy?->name,
            'beneficiaries' => GrantBeneficiaryResource::collection($this->whenLoaded('beneficiaries')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
