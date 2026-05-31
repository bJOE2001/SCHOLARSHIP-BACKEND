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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApplicationController extends Controller
{
    /**
     * List applications and attached documents.
     */
    public function index(Request $request): JsonResponse
    {
        $applications = $this->visibleApplicationsQuery($request)
            ->with(['documents', 'applicant', 'program', 'reviewer'])
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
     * List applications currently available in the officer review workspace.
     */
    public function review(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $programId = (int) $request->query('programId', 0);

        $applications = $this->visibleApplicationsQuery($request)
            ->with(['documents', 'applicant', 'program', 'reviewer'])
            ->whereIn('status', $this->reviewStatuses())
            ->when(in_array($status, $this->reviewStatuses(), true), fn (Builder $query) => $query->where('status', $status))
            ->when($programId > 0, fn (Builder $query) => $query->where('scholarship_program_id', $programId))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('application_no', 'like', "%{$search}%")
                        ->orWhereHas('applicant', function (Builder $applicantQuery) use ($search): void {
                            $applicantQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->orWhereHas('program', function (Builder $programQuery) use ($search): void {
                            $programQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('provider', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('submitted_at')
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
        $studentId = $currentUser?->isSuperAdmin() ? (int) $validated['studentId'] : (int) $currentUser->id;
        $programId = (int) $validated['programId'];
        $student = User::query()->findOrFail($studentId);
        $program = ScholarshipProgram::query()->findOrFail($programId);

        $this->syncStudentProfile($student, $validated);

        $currentCycleApplication = ScholarshipApplication::query()
            ->where('applicant_id', $student->id)
            ->where('scholarship_program_id', $program->id)
            ->latest()
            ->get()
            ->first(fn (ScholarshipApplication $application): bool => $this->belongsToCurrentProgramCycle($application, $program));

        if ($currentCycleApplication !== null && in_array($currentCycleApplication->status, ['Rejected', 'Ineligible', 'Terminated'], true)) {
            throw ValidationException::withMessages([
                'programId' => ['You cannot apply for this scholarship again yet. Please wait until applications close and reopen for a new application period.'],
            ]);
        }

        $application = $currentCycleApplication;

        if ($application === null) {
            $application = ScholarshipApplication::create([
                'applicant_id' => $student->id,
                'scholarship_program_id' => $program->id,
                'application_no' => $this->buildApplicationNumber($student->id, $program->id),
                'status' => 'Draft',
                'risk_label' => 'Stable',
                'score' => 0,
                'progress' => 0,
                'remarks' => 'Draft application created.',
                'next_action' => 'Complete the scholarship form.',
                'missing_requirements' => $program->requirementNames(),
                'timeline' => [
                    [
                        'status' => 'Draft',
                        'label' => 'Draft Created',
                        'remarks' => 'Draft application created.',
                        'date' => now()->toISOString(),
                    ],
                ],
            ]);
            $this->seedRequirementDocuments($application, $program);
        }

        return response()->json([
            'application' => new ScholarshipApplicationResource($application->load(['documents', 'applicant', 'program', 'reviewer'])),
        ], $application->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Update the status of an application.
     */
    public function updateStatus(UpdateApplicationStatusRequest $request, ScholarshipApplication $application): JsonResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();
        $removedApplicationIds = [];

        abort_unless($this->canAccessApplication($currentUser, $application), 403);

        DB::transaction(function () use ($application, $currentUser, $validated, &$removedApplicationIds): void {
            $application->fill([
                'status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? $application->remarks,
                'next_action' => $this->nextActionForStatus($validated['status']),
                'progress' => $this->progressForStatus($validated['status']),
                'score' => $this->scoreForStatus($validated['status'], (int) $application->score),
                'risk_label' => $this->riskLabelForStatus($validated['status']),
                'reviewed_by_id' => $currentUser?->id,
                'reviewed_at' => now(),
            ]);

            $application->appendTimelineEvent($validated['status'], $validated['remarks'] ?? $application->remarks ?? 'Status updated.');
            $application->save();

            $this->syncProgramUsage($application->program);

            if (in_array($application->status, $this->activeStatuses(), true)) {
                $this->syncScholarRecord($application);
                $removedApplicationIds = $this->removeOtherPendingApplications($application);
            }
        });

        $this->notifyApplicantOfStatus($application, $validated['remarks'] ?? null);

        return response()->json([
            'application' => new ScholarshipApplicationResource($application->load(['documents', 'applicant', 'program', 'reviewer'])),
            'removedApplicationIds' => $removedApplicationIds,
        ]);
    }

    /**
     * Upload or replace a requirement document.
     */
    public function storeDocument(UploadApplicationDocumentRequest $request, ScholarshipApplication $application): JsonResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();

        abort_unless($this->canAccessApplication($currentUser, $application), 403);

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

        $this->notifyOfficersOfDocumentUpload($document->refresh(), $application->fresh(['program']));

        return response()->json([
            'application' => new ScholarshipApplicationResource($application->fresh(['documents', 'applicant', 'program', 'reviewer'])),
            'documents' => ApplicationDocumentResource::collection($application->documents()->orderBy('name')->get()),
        ], 201);
    }

    /**
     * Submit a completed student application for review.
     */
    public function submit(Request $request, ScholarshipApplication $application): JsonResponse
    {
        $currentUser = $request->user();

        abort_unless($this->canAccessApplication($currentUser, $application), 403);

        if (! in_array($application->status, ['Draft', 'For Revision', 'Needs Revision'], true)) {
            return response()->json([
                'message' => 'Only draft or revision applications can be submitted.',
            ], 422);
        }

        $application->loadMissing(['documents', 'applicant', 'program']);
        $missingRequirements = $this->missingUploadedRequirements($application);
        $requiredMissingRequirements = $this->missingUploadedRequirements($application, true);

        if ($requiredMissingRequirements !== []) {
            $application->syncMissingRequirements($missingRequirements);
            $application->save();

            return response()->json([
                'message' => 'Please upload all required documents before submitting.',
                'errors' => [
                    'documents' => $requiredMissingRequirements,
                ],
            ], 422);
        }

        $nextStatus = in_array($application->status, ['For Revision', 'Needs Revision'], true) ? 'Resubmitted' : 'Submitted';
        $remarks = 'Application submitted for review.';
        $application->fill([
            'status' => $nextStatus,
            'risk_label' => $this->riskLabelForStatus($nextStatus),
            'progress' => $this->progressForStatus($nextStatus),
            'remarks' => $remarks,
            'next_action' => $this->nextActionForStatus($nextStatus),
            'missing_requirements' => $missingRequirements,
            'submitted_at' => now(),
        ]);
        $application->appendTimelineEvent($nextStatus, $remarks);
        $application->save();

        $this->notifyOfficersOfSubmittedApplication($application->fresh(['program']));

        return response()->json([
            'application' => new ScholarshipApplicationResource($application->fresh(['documents', 'applicant', 'program', 'reviewer'])),
        ]);
    }

    /**
     * Resolve the applications visible to the current user.
     */
    private function visibleApplicationsQuery(Request $request): Builder
    {
        $currentUser = $request->user();

        return ScholarshipApplication::query()
            ->when($currentUser?->isStudent(), fn ($query) => $query->where('applicant_id', $currentUser->id))
            ->when($currentUser?->isOfficer() && ! $currentUser?->isSuperAdmin(), function ($query) use ($currentUser): void {
                $programIds = $this->assignedProgramIds($currentUser);

                $programIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('scholarship_program_id', $programIds);
            });
    }

    /**
     * Check whether the signed-in user can manage an application.
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
            return in_array((int) $application->scholarship_program_id, $this->assignedProgramIds($user), true);
        }

        return $application->applicant_id === $user->id;
    }

    /**
     * Return assigned scholarship program ids for an officer.
     *
     * @return array<int, int>
     */
    private function assignedProgramIds(User $user): array
    {
        return array_values(array_map('intval', $user->assigned_program_ids ?? []));
    }

    /**
     * Save review-facing applicant profile details supplied during application.
     *
     * @param  array<string, mixed>  $validated
     */
    private function syncStudentProfile(User $student, array $validated): void
    {
        $attributeMap = [
            'name' => 'name',
            'gender' => 'gender',
            'birthDate' => 'birth_date',
            'civilStatus' => 'civil_status',
            'citizenship' => 'citizenship',
            'address' => 'address',
            'barangay' => 'barangay',
            'city' => 'city',
            'province' => 'province',
            'contactNumber' => 'contact_number',
            'campus' => 'campus',
            'schoolName' => 'school_name',
            'applicantStudentId' => 'student_id',
            'course' => 'course',
            'yearLevel' => 'year_level',
            'semester' => 'semester',
            'academicYear' => 'academic_year',
            'gpa' => 'gpa',
            'familyIncome' => 'family_income',
            'enrollmentStatus' => 'enrollment_status',
            'academicAwards' => 'academic_awards',
            'fatherName' => 'father_name',
            'motherName' => 'mother_name',
            'guardianName' => 'guardian_name',
            'parentOccupation' => 'parent_occupation',
            'monthlyIncome' => 'monthly_income',
            'siblings' => 'siblings',
            'studyingSiblings' => 'studying_siblings',
            'incomeBracket' => 'income_bracket',
        ];

        $attributes = [];

        foreach ($attributeMap as $payloadKey => $databaseColumn) {
            if (array_key_exists($payloadKey, $validated)) {
                $attributes[$databaseColumn] = $validated[$payloadKey];
            }
        }

        if ($attributes === []) {
            return;
        }

        $student->fill($attributes);

        if ($student->isDirty()) {
            $student->save();
        }
    }

    /**
     * Create missing document placeholders for a draft application.
     */
    private function seedRequirementDocuments(ScholarshipApplication $application, ScholarshipProgram $program): void
    {
        foreach ($program->requirementNames() as $requirement) {
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
     * Return program requirements that do not have an uploaded file yet.
     *
     * @return array<int, string>
     */
    private function missingUploadedRequirements(ScholarshipApplication $application, bool $requiredForSubmissionOnly = false): array
    {
        $documents = $application->documents->keyBy('name');
        $requirementNames = $requiredForSubmissionOnly
            ? $application->program?->requiredApplicationRequirementNames() ?? []
            : $application->program?->requirementNames() ?? [];

        return collect($requirementNames)
            ->filter(function (string $requirement) use ($documents): bool {
                $document = $documents->get($requirement);

                return $document === null || $document->path === null;
            })
            ->values()
            ->all();
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
                'scholarship_status' => 'Active Scholar',
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
     * Remove a newly approved scholar's other still-pending applications.
     *
     * @return array<int, int>
     */
    private function removeOtherPendingApplications(ScholarshipApplication $approvedApplication): array
    {
        $removableStatuses = [
            'Draft',
            'Submitted',
            'For Revision',
            'Needs Revision',
            'Resubmitted',
            'Under Review',
            'Application Review Approved',
            'Eligible',
            'Shortlisted',
        ];

        $applications = ScholarshipApplication::query()
            ->where('applicant_id', $approvedApplication->applicant_id)
            ->where('id', '!=', $approvedApplication->id)
            ->where('scholarship_program_id', '!=', $approvedApplication->scholarship_program_id)
            ->whereIn('status', $removableStatuses)
            ->get(['id', 'scholarship_program_id']);

        if ($applications->isEmpty()) {
            return [];
        }

        $programIds = $applications
            ->pluck('scholarship_program_id')
            ->push($approvedApplication->scholarship_program_id)
            ->unique()
            ->values();
        $applicationIds = $applications->pluck('id')->map(fn (int $id): int => $id)->all();

        ScholarshipApplication::query()
            ->whereIn('id', $applicationIds)
            ->delete();

        ScholarshipProgram::query()
            ->whereIn('id', $programIds)
            ->get()
            ->each(fn (ScholarshipProgram $program) => $this->syncProgramUsage($program));

        return $applicationIds;
    }

    /**
     * Build the active scholar submission list from the current documents.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSubmissions(ScholarshipApplication $application): array
    {
        $documents = $application->documents->keyBy('name');
        $requirements = $application->program?->requirementNames() ?? [];

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
     * A reopened program starts a fresh application cycle for rejected applicants.
     */
    private function belongsToCurrentProgramCycle(ScholarshipApplication $application, ScholarshipProgram $program): bool
    {
        if ($program->published_at === null) {
            return true;
        }

        return $application->created_at?->greaterThanOrEqualTo($program->published_at) ?? true;
    }

    /**
     * Return the configured active application statuses.
     *
     * @return array<int, string>
     */
    private function activeStatuses(): array
    {
        return [
            'Approved',
            'Accepted',
            'Enrollment Verified',
            'Active Scholar',
            'Pending Renewal',
            'Under Renewal Review',
            'Probation',
            'Suspended',
            'Renewal Pending',
            'Renewed',
        ];
    }

    /**
     * Return application statuses available for officer review.
     *
     * @return array<int, string>
     */
    private function reviewStatuses(): array
    {
        return [
            'Submitted',
            'Under Review',
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
            'Under Review' => 50,
            'Application Review Approved' => 60,
            'Eligible' => 75,
            'Shortlisted' => 90,
            'Approved' => 100,
            'Rejected' => 0,
            'For Revision', 'Needs Revision' => 35,
            'Resubmitted' => 40,
            'Accepted' => 100,
            'Enrollment Verified' => 90,
            'Active Scholar' => 100,
            'Pending Renewal', 'Renewal Pending' => 95,
            'Under Renewal Review' => 95,
            'Probation', 'Suspended' => 100,
            'Renewed' => 100,
            'Terminated' => 100,
            default => 50,
        };
    }

    /**
     * Keep a reasonable evaluation score in sync with review milestones.
     */
    private function scoreForStatus(string $status, int $currentScore): int
    {
        return match ($status) {
            'Eligible' => max($currentScore, 80),
            'Shortlisted', 'Approved' => max($currentScore, 90),
            'Rejected' => $currentScore,
            default => $currentScore,
        };
    }

    /**
     * Determine the risk label for a status.
     */
    private function riskLabelForStatus(string $status): string
    {
        return match ($status) {
            'Rejected', 'Terminated', 'Suspended' => 'High Risk',
            'Probation' => 'Medium Risk',
            'For Revision', 'Needs Revision' => 'At Risk',
            'Submitted', 'Resubmitted', 'Under Review', 'Application Review Approved', 'Renewal Pending', 'Pending Renewal', 'Under Renewal Review' => 'Medium Risk',
            default => 'Low Risk',
        };
    }

    /**
     * Determine the compliance status for a scholar.
     */
    private function complianceStatusForApplication(string $status): string
    {
        return match ($status) {
            'Rejected', 'Terminated', 'Suspended' => 'Non-Compliant',
            'For Revision', 'Needs Revision' => 'Incomplete',
            'Submitted', 'Resubmitted', 'Under Review', 'Application Review Approved', 'Under Renewal Review' => 'Incomplete',
            'Renewal Pending', 'Pending Renewal', 'Probation' => 'Late Submission',
            default => 'Compliant',
        };
    }

    /**
     * Determine the renewal status for a scholar.
     */
    private function renewalStatusForApplication(string $status): string
    {
        return match ($status) {
            'Renewed' => 'Renewed',
            'Pending Renewal', 'Renewal Pending' => 'Pending Renewal',
            'Under Renewal Review' => 'Under Renewal Review',
            'Probation' => 'Probation',
            'Suspended' => 'Suspended',
            'Terminated' => 'Terminated',
            'Enrollment Verified' => 'Under Renewal Review',
            default => 'Active Scholar',
        };
    }

    /**
     * Determine the next action text for a status.
     */
    private function nextActionForStatus(string $status): string
    {
        return match ($status) {
            'Submitted' => 'Wait for initial screening.',
            'Resubmitted' => 'Review resubmitted requirements.',
            'Under Review' => 'Review documents and compute scores.',
            'Application Review Approved' => 'Validate all submitted documents.',
            'Eligible' => 'Shortlist the applicant when ready.',
            'Shortlisted' => 'Prepare final scholarship approval.',
            'Approved' => 'Scholar approved successfully.',
            'For Revision', 'Needs Revision' => 'Ask the student to resubmit requirements.',
            'Accepted' => 'Prepare award and enrollment verification.',
            'Rejected' => 'Notify the applicant of the decision.',
            'Enrollment Verified' => 'Onboard the scholar to active monitoring.',
            'Active Scholar' => 'Continue compliance monitoring.',
            'Pending Renewal', 'Renewal Pending' => 'Collect renewal requirements.',
            'Under Renewal Review' => 'Review renewal requirements.',
            'Probation' => 'Monitor the scholar under probation.',
            'Suspended' => 'Review the suspension recommendation.',
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
            'For Revision', 'Needs Revision' => 'Requirements are still incomplete.',
            'Under Review' => 'Application is under active review.',
            'Application Review Approved' => 'Application review is approved and awaiting document validation.',
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
        $stage = $application->status === 'Rejected' ? $this->rejectionStageForApplication($application) : null;
        $message = $stage !== null
            ? "Your application for {$programName} was rejected during {$stage}."
            : "Your application for {$programName} is now {$application->status}.";

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
     * Notify officers that an application is ready for review.
     */
    private function notifyOfficersOfSubmittedApplication(ScholarshipApplication $application): void
    {
        $application->loadMissing('program');
        $programName = $application->program?->name ?? 'a scholarship program';

        ScholarshipNotification::create([
            'user_id' => null,
            'role' => 'officer',
            'type' => 'task',
            'title' => 'New Application Submitted',
            'message' => "{$application->application_no} for {$programName} is ready for initial screening.",
            'notified_at' => now(),
            'payload' => [
                'applicationId' => $application->id,
                'programId' => $application->scholarship_program_id,
                'status' => $application->status,
            ],
        ]);
    }

    /**
     * Notify officers that a requirement document needs validation.
     */
    private function notifyOfficersOfDocumentUpload(ApplicationDocument $document, ScholarshipApplication $application): void
    {
        $application->loadMissing('program');
        $programName = $application->program?->name ?? 'a scholarship program';

        ScholarshipNotification::create([
            'user_id' => null,
            'role' => 'officer',
            'type' => 'task',
            'title' => 'Document Pending Validation',
            'message' => "{$document->name} for {$application->application_no} ({$programName}) is awaiting validation.",
            'notified_at' => now(),
            'payload' => [
                'applicationId' => $application->id,
                'documentId' => $document->id,
                'programId' => $application->scholarship_program_id,
            ],
        ]);
    }

    /**
     * Map an application status to the notification style used by the UI.
     */
    private function notificationTypeForStatus(string $status): string
    {
        return match ($status) {
            'Approved', 'Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewed' => 'success',
            'For Revision', 'Needs Revision', 'Renewal Pending' => 'task',
            'Rejected', 'Terminated' => 'warning',
            default => 'status',
        };
    }

    /**
     * Infer the review stage where an application rejection happened.
     */
    private function rejectionStageForApplication(ScholarshipApplication $application): ?string
    {
        $previousStatus = collect($application->timeline ?? [])
            ->reverse()
            ->pluck('status')
            ->first(fn (?string $status): bool => $status !== null && ! in_array($status, ['Rejected', 'Ineligible', 'Terminated'], true));

        return match ($previousStatus) {
            'Submitted', 'Resubmitted', 'For Revision', 'Needs Revision' => 'Application Management',
            'Under Review' => 'Application Review',
            'Application Review Approved' => 'Document Validation',
            'Eligible' => 'Applicant Ranking',
            'Shortlisted' => 'Final Decision',
            default => null,
        };
    }
}
