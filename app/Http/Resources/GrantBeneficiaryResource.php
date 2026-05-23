<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantBeneficiaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $batch = $this->relationLoaded('batch') ? $this->batch : null;
        $program = $batch?->relationLoaded('program') ? $batch->program : null;

        return [
            'id' => $this->id,
            'batchId' => $this->grant_batch_id,
            'batchTitle' => $batch?->title,
            'scholarRecordId' => $this->scholar_id,
            'scholarId' => $this->scholar_identifier,
            'userId' => $this->user_id,
            'scholarName' => $this->scholar_name,
            'barangay' => $this->barangay,
            'course' => $this->course,
            'programId' => $batch?->scholarship_program_id,
            'programName' => $program?->name,
            'semester' => $batch?->semester,
            'schoolYear' => $batch?->school_year,
            'venue' => $batch?->venue,
            'amount' => $this->amount,
            'assignedClaimDate' => $this->assigned_claim_date?->toDateString(),
            'timeSlot' => $this->time_slot,
            'claimStatus' => $this->claim_status,
            'notifiedAt' => $this->notified_at?->toISOString(),
            'claimedAt' => $this->claimed_at?->toISOString(),
            'releasedBy' => $this->released_by_name,
            'referenceNumber' => $this->reference_number,
            'qrCode' => $this->reference_number,
            'claimMethod' => $this->claim_method,
            'releaseRemarks' => $this->release_remarks,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
