<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantAnnouncementResource extends JsonResource
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
            'batchId' => $this->grant_batch_id,
            'title' => $this->title,
            'message' => $this->message,
            'programName' => $this->program_name,
            'semester' => $this->semester,
            'schoolYear' => $this->school_year,
            'venue' => $this->venue,
            'remarks' => $this->remarks ?? $this->whenLoaded('batch', fn () => $this->batch?->remarks),
            'totalBeneficiaries' => $this->total_beneficiaries,
            'createdBy' => $this->created_by_name,
            'batch' => new GrantBatchResource($this->whenLoaded('batch')),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
