<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationDraftRequest;
use App\Http\Requests\UpdateApplicationStatusRequest;
use App\Http\Requests\UploadApplicationDocumentRequest;
use App\Http\Resources\ApplicationDocumentResource;
use App\Http\Resources\ScholarshipApplicationResource;
use App\Models\ApplicationDocument;
use App\Models\Scholar;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipNotification;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    /**
     * List applications and attached documents.
     */
    public function index(Request $request): JsonResponse
    {
        $applications = $this->visibleApplicationsQuery($request)
            ->with(['documents', 'applicant', 'program'])
            ->latest()
            ->get();

        $documents = $applications
            ->flatMap(fn (ScholarshipApplication $application) => $application->documents)
            ->values();

        return response()->json([
            'applications' => ScholarshipApplicationResource::collection($applications),
            'documents' => ApplicationDocumentResource::collection($documents),
        ]);
    }

    /**
     * Create or reuse a draft application.
     */
    public function draft(StoreApplicationDraftRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();
        $studentId = $currentUser?->isAdmin() ? (int) $validated['studentId'] : (int) $currentUser->id;
        $programId = (int) $validated['programId'];
        $student = User::query()->findOrFail($studentId);
        $program = ScholarshipProgram::query()->findOrFail($programId);

        $application = ScholarshipApplication::firstOrCreate(
            [
                'applicant_id' => $student->id,
                'scholarship_program_id' => $program->id,
            ],
            [
                'application_no' => $this->buildApplicationNumber($student->id, $program->id),
                'status' => 'Draft',
                'risk_label' => 'Stable',
                'score' => 0,
                'progress' => 0,
                'remarks' => 'Draft application created.',
                'next_action' => 'Complete the scholarship form.',
                'missing_requirements' => $program->requirements ?? [],
                'timeline' => [
                    [
                        'status' => 'Draft',
                        'label' => 'Draft Created',
                        'remarks' => 'Draft application created.',
                        'date' => now()->format('M d, Y'),
                    ],
                ],
            ],
        );

        if ($application->wasRecentlyCreated) {
            $this->seedRequirementDocuments($application, $program);
        }

        return response()->json([
            'application' => new ScholarshipApplicationResource($application->load(['documents', 'applicant', 'program'])),
        ], $application->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Update the status of an application.
     */
    public function updateStatus(UpdateApplicationStatusRequest $request, ScholarshipApplication $application): JsonResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();

        $application->fill([
            'status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? $application->remarks,
            'next_action' => $this->nextActionForStatus($validated['status']),
            'progress' => $this->progressForStatus($validated['status']),
            'risk_label' => $this->riskLabelForStatus($validated['status']),
            'reviewed_by_id' => $currentUser?->id,
            'reviewed_at' => now(),
        ]);

        $statusChanged = $application->isDirty('status');
        $remarksChanged = array_key_exists('remarks', $validated) && $application->isDirty('remarks');

        $application->appendTimelineEvent($validated['status'], $validated['remarks'] ?? $application->remarks ?? 'Status updated.');
        $application->save();

        $this->syncProgramUsage($application->program);

        if (in_array($application->status, $this->activeStatuses(), true)) {
            $this->syncScholarRecord($application);
        }

        if ($statusChanged || $remarksChanged) {
            $this->notifyApplicantOfStatus($application, $validated['remarks'] ?? null);
        }

        return response()->json([
            'application' => new ScholarshipApplicationResource($application->load(['documents', 'applicant', 'program'])),
        ]);
    }

    /**
     * Upload or replace a requirement document.
     */
    public function storeDocument(UploadApplicationDocumentRequest $request, ScholarshipApplication $application): JsonResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();

        abort_unless(
            $currentUser?->isAdmin() || $application->applicant_id === $currentUser?->id,
            403,
        );

        $file = $request->file('file');
        $documentPath = null;
        $documentType = 'FILE';

        if ($file !== null) {
            $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
            $documentType = strtoupper($extension);
            $fileName = Str::slug($validated['requirementName']).'-'.Str::random(8).'.'.$extension;
            $documentPath = Storage::disk('local')->putFileAs(
                "application-documents/{$application->id}",
                $file,
                $fileName,
            );
        }

        $document = ApplicationDocument::updateOrCreate(
            [
                'scholarship_application_id' => $application->id,
                'name' => $validated['requirementName'],
            ],
            [
                'type' => $documentType,
                'path' => $documentPath,
                'status' => 'Pending',
                'remarks' => 'Uploaded and awaiting validation.',
                'uploaded_by_id' => $request->user()?->id,
                'validated_by_id' => null,
                'uploaded_at' => now(),
            ],
        );

        $applicationRequirements = $application->missing_requirements ?? [];
        $applicationRequirements = array_values(array_filter(
            $applicationRequirements,
            static fn (string $requirement): bool => $requirement !== $validated['requirementName'],
        ));
        $application->update([
            'missing_requirements' => $applicationRequirements,
        ]);

        return response()->json([
            'application' => new ScholarshipApplicationResource($application->fresh(['documents', 'applicant', 'program'])),
            'documents' => ApplicationDocumentResource::collection($application->documents()->orderBy('name')->get()),
        ], 201);
    }

    /**
     * Resolve the applications visible to the current user.
     */
    private function visibleApplicationsQuery(Request $request): Builder
    {
        $currentUser = $request->user();

        return ScholarshipApplication::query()
            ->when($currentUser?->isStudent(), fn ($query) => $query->where('applicant_id', $currentUser->id));
    }

    /**
     * Create missing document placeholders for a draft application.
     */
    private function seedRequirementDocuments(ScholarshipApplication $application, ScholarshipProgram $program): void
    {
        foreach ($program->requirements ?? [] as $requirement) {
            ApplicationDocument::firstOrCreate(
                [
                    'scholarship_application_id' => $application->id,
                    'name' => $requirement,
                ],
                [
                    'type' => 'PDF',
                    'path' => null,
                    'status' => 'Missing',
                    'remarks' => 'Awaiting upload.',
                    'uploaded_by_id' => null,
                    'validated_by_id' => null,
                    'uploaded_at' => null,
                ],
            );
        }
    }

    /**
     * Keep the scholarship program slot usage in sync with approved applications.
     */
    private function syncProgramUsage(ScholarshipProgram $program): void
    {
        $usedSlots = ScholarshipApplication::query()
            ->where('scholarship_program_id', $program->id)
            ->whereIn('status', $this->activeStatuses())
            ->count();

        $program->update([
            'used_slots' => $usedSlots,
        ]);
    }

    /**
     * Sync a scholar record from an approved application.
     */
    private function syncScholarRecord(ScholarshipApplication $application): void
    {
        $application->loadMissing(['applicant', 'program', 'documents']);
        $applicant = $application->applicant;
        $program = $application->program;

        if ($applicant === null || $program === null) {
            return;
        }

        $submissions = $this->buildSubmissions($application);
        $scholar = Scholar::updateOrCreate(
            [
                'scholarship_application_id' => $application->id,
            ],
            [
                'user_id' => $applicant->id,
                'scholarship_program_id' => $program->id,
                'scholar_id' => $this->buildScholarNumber($application),
                'name' => $applicant->name,
                'avatar' => $applicant->avatar,
                'program' => $program->name,
                'course' => $applicant->course,
                'year_level' => $applicant->year_level,
                'school' => $applicant->school_name ?: $applicant->campus,
                'gender' => $applicant->gender,
                'birthdate' => $applicant->birth_date,
                'address' => $applicant->address,
                'contact_number' => $applicant->contact_number,
                'email' => $applicant->email,
                'gpa' => $applicant->gpa,
                'enrollment_status' => $applicant->enrollment_status,
                'academic_year' => $applicant->academic_year,
                'semester' => $applicant->semester,
                'scholarship_status' => 'Active',
                'renewal_status' => $this->renewalStatusForApplication($application->status),
                'date_approved' => now(),
                'duration' => '1 Academic Year',
                'compliance_status' => $this->complianceStatusForApplication($application->status),
                'compliance_rate' => 100,
                'risk_label' => $this->riskLabelForStatus($application->status),
                'risk_reason' => $this->riskReasonForApplication($application),
                'recommended_action' => $this->nextActionForStatus($application->status),
                'submissions' => $submissions,
            ],
        );

        $scholar->update([
            'submissions' => $submissions,
        ]);
    }

    /**
     * Build the active scholar submission list from the current documents.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSubmissions(ScholarshipApplication $application): array
    {
        $documents = $application->documents->keyBy('name');
        $requirements = $application->program?->requirements ?? [];

        return collect($requirements)
            ->map(function (string $requirement) use ($documents): array {
                $document = $documents->get($requirement);

                return [
                    'requirement' => $requirement,
                    'status' => $document?->status ?? 'Missing',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Generate a scholar id from the application.
     */
    private function buildScholarNumber(ScholarshipApplication $application): string
    {
        return 'SCH-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate an application number from the applicant and program.
     */
    private function buildApplicationNumber(int $studentId, int $programId): string
    {
        return 'APP-'.$programId.'-'.$studentId.'-'.Str::upper(Str::random(4));
    }

    /**
     * Return the configured active application statuses.
     *
     * @return array<int, string>
     */
    private function activeStatuses(): array
    {
        return [
            'Accepted',
            'Enrollment Verified',
            'Active Scholar',
            'Renewal Pending',
            'Renewed',
        ];
    }

    /**
     * Determine the progress value for a status.
     */
    private function progressForStatus(string $status): int
    {
        return match ($status) {
            'Draft' => 0,
            'Submitted' => 25,
            'Under Review' => 45,
            'Needs Revision' => 55,
            'Accepted' => 80,
            'Rejected' => 100,
            'Enrollment Verified' => 90,
            'Active Scholar' => 100,
            'Renewal Pending' => 95,
            'Renewed' => 100,
            'Terminated' => 100,
            default => 50,
        };
    }

    /**
     * Determine the risk label for a status.
     */
    private function riskLabelForStatus(string $status): string
    {
        return match ($status) {
            'Rejected', 'Terminated' => 'Critical',
            'Needs Revision' => 'At Risk',
            'Submitted', 'Under Review', 'Renewal Pending' => 'Borderline',
            default => 'Stable',
        };
    }

    /**
     * Determine the compliance status for a scholar.
     */
    private function complianceStatusForApplication(string $status): string
    {
        return match ($status) {
            'Rejected', 'Terminated' => 'Non-Compliant',
            'Needs Revision' => 'Missing Requirements',
            'Submitted', 'Under Review' => 'Pending Review',
            default => 'Complete',
        };
    }

    /**
     * Determine the renewal status for a scholar.
     */
    private function renewalStatusForApplication(string $status): string
    {
        return match ($status) {
            'Renewed' => 'Renewed',
            'Renewal Pending' => 'Renewal Pending',
            'Terminated' => 'Terminated',
            'Enrollment Verified' => 'Under Evaluation',
            default => 'Active',
        };
    }

    /**
     * Determine the next action text for a status.
     */
    private function nextActionForStatus(string $status): string
    {
        return match ($status) {
            'Submitted' => 'Wait for initial screening.',
            'Under Review' => 'Review documents and compute scores.',
            'Needs Revision' => 'Ask the student to resubmit requirements.',
            'Accepted' => 'Prepare award and enrollment verification.',
            'Rejected' => 'Notify the applicant of the decision.',
            'Enrollment Verified' => 'Onboard the scholar to active monitoring.',
            'Active Scholar' => 'Continue compliance monitoring.',
            'Renewal Pending' => 'Collect renewal requirements.',
            'Renewed' => 'Keep the scholar under renewal monitoring.',
            'Terminated' => 'Close the case and archive the record.',
            default => 'Continue reviewing the application.',
        };
    }

    /**
     * Build a risk reason for the scholar.
     */
    private function riskReasonForApplication(ScholarshipApplication $application): string
    {
        return match ($application->status) {
            'Rejected', 'Terminated' => 'Application was not approved.',
            'Needs Revision' => 'Requirements are still incomplete.',
            'Under Review' => 'Application is under active review.',
            'Renewal Pending' => 'Scholar is waiting for renewal verification.',
            default => 'Scholarship record is in a healthy state.',
        };
    }

    /**
     * Notify a student about an application decision or review update.
     */
    private function notifyApplicantOfStatus(ScholarshipApplication $application, ?string $remarks): void
    {
        $application->loadMissing('program');
        $programName = $application->program?->name ?? 'your scholarship';
        $message = "Your application for {$programName} is now {$application->status}.";

        if ($remarks !== null && trim($remarks) !== '') {
            $message .= ' Remarks: '.trim($remarks);
        }

        ScholarshipNotification::create([
            'user_id' => $application->applicant_id,
            'role' => null,
            'type' => $this->notificationTypeForStatus($application->status),
            'title' => 'Application Status Updated',
            'message' => $message,
            'notified_at' => now(),
            'payload' => [
                'applicationId' => $application->id,
                'programId' => $application->scholarship_program_id,
                'status' => $application->status,
            ],
        ]);
    }

    /**
     * Map an application status to the notification style used by the UI.
     */
    private function notificationTypeForStatus(string $status): string
    {
        return match ($status) {
            'Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewed' => 'success',
            'Needs Revision', 'Renewal Pending' => 'task',
            'Rejected', 'Terminated' => 'warning',
            default => 'status',
        };
    }
}
