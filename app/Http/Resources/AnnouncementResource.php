<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $programName = $this->whenLoaded('program', fn (): ?string => $this->program?->name);
        $createdByName = $this->whenLoaded('createdBy', fn (): ?string => $this->createdBy?->name);

        return [
            'id' => $this->id,
            'programId' => $this->scholarship_program_id,
            'scholarshipProgramId' => $this->scholarship_program_id,
            'programName' => $programName,
            'program' => $programName,
            'createdById' => $this->created_by_id,
            'createdByName' => $createdByName,
            'title' => $this->title,
            'message' => $this->message,
            'pin' => $this->pin,
            'status' => $this->status,
            'publishedAt' => $this->published_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
