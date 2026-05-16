<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScholarshipNotificationResource extends JsonResource
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
            'role' => $this->role,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'date' => $this->notified_at?->toISOString() ?? $this->created_at?->toISOString(),
            'read' => $this->read_at !== null,
            'readAt' => $this->read_at?->toISOString(),
            'payload' => $this->payload ?? [],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
