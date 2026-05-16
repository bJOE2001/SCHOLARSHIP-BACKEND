<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationDocumentResource extends JsonResource
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
            'applicationId' => $this->scholarship_application_id,
            'name' => $this->name,
            'type' => $this->type,
            'path' => $this->path,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'uploadedById' => $this->uploaded_by_id,
            'validatedById' => $this->validated_by_id,
            'uploadedAt' => $this->uploaded_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
