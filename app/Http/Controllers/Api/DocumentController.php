<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDocumentStatusRequest;
use App\Http\Resources\ApplicationDocumentResource;
use App\Models\ApplicationDocument;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    /**
     * Stream one uploaded requirement file from private storage.
     */
    public function showFile(ApplicationDocument $document): StreamedResponse
    {
        $application = $document->application()->first();

        abort_unless($application !== null && $this->canAccessApplication(request()->user(), $application), 403);
        abort_if($document->path === null || ! Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response(
            $document->path,
            basename($document->path),
        );
    }

    /**
     * Update one application document status.
     */
    public function updateStatus(UpdateDocumentStatusRequest $request, ApplicationDocument $document): JsonResponse
    {
        $validated = $request->validated();
        $application = $document->application()->with('documents', 'program')->first();

        abort_unless($application !== null && $this->canAccessApplication($request->user(), $application), 403);

        $statusChanged = $document->status !== $validated['status'];
        $remarksChanged = array_key_exists('remarks', $validated) && ($validated['remarks'] ?? null) !== $document->remarks;

        $document->update([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? $document->remarks,
            'validated_by_id' => $request->user()?->id,
        ]);

        if ($application !== null) {
            $missingRequirements = $application->missing_requirements ?? [];

            if ($validated['status'] === 'Accepted') {
                $missingRequirements = array_values(array_filter(
                    $missingRequirements,
                    static fn (string $requirement): bool => $requirement !== $document->name,
                ));
            } elseif (in_array($validated['status'], ['Needs Revision', 'Rejected', 'Missing'], true)) {
                $missingRequirements[] = $document->name;
                $missingRequirements = array_values(array_unique($missingRequirements));
            }

            $application->update([
                'missing_requirements' => $missingRequirements,
            ]);

            if ($statusChanged || $remarksChanged) {
                $this->notifyApplicantOfDocumentStatus($document->refresh(), $application, $validated['remarks'] ?? null);
            }
        }

        return response()->json([
            'document' => new ApplicationDocumentResource($document->refresh()),
        ]);
    }

    /**
     * Notify a student about one document validation update.
     */
    private function notifyApplicantOfDocumentStatus(
        ApplicationDocument $document,
        ScholarshipApplication $application,
        ?string $remarks,
    ): void {
        $programName = $application->program?->name ?? 'your scholarship application';
        $isRevisionRequest = $document->status === 'Needs Revision';
        $title = $isRevisionRequest ? 'Document Revision Requested' : 'Document Status Updated';
        $message = match ($document->status) {
            'Needs Revision' => "Your {$document->name} document for {$programName} needs revision during Document Validation. Please upload a corrected file.",
            'Rejected' => "Your {$document->name} document for {$programName} was rejected during Document Validation.",
            default => "Your {$document->name} document for {$programName} is now {$document->status}.",
        };

        if ($remarks !== null && trim($remarks) !== '') {
            $message .= ' Remarks: '.trim($remarks);
        }

        ScholarshipNotification::create([
            'user_id' => $application->applicant_id,
            'role' => null,
            'type' => $this->notificationTypeForDocumentStatus($document->status),
            'title' => $title,
            'message' => $message,
            'notified_at' => now(),
            'payload' => [
                'applicationId' => $application->id,
                'documentId' => $document->id,
                'status' => $document->status,
            ],
        ]);
    }

    /**
     * Map a document status to the notification style used by the UI.
     */
    private function notificationTypeForDocumentStatus(string $status): string
    {
        return match ($status) {
            'Accepted' => 'success',
            'Needs Revision', 'Rejected', 'Missing' => 'warning',
            default => 'status',
        };
    }

    /**
     * Check whether the signed-in user can access this application's documents.
     */
    private function canAccessApplication(?User $user, ScholarshipApplication $application): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isOfficer()) {
            $programIds = array_values(array_map('intval', $user->assigned_program_ids ?? []));

            return in_array((int) $application->scholarship_program_id, $programIds, true);
        }

        return $application->applicant_id === $user->id;
    }
}
