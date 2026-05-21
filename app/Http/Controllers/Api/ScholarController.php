<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequirementRequest;
use App\Http\Requests\UpdateScholarComplianceRequest;
use App\Http\Resources\ScholarResource;
use App\Models\ApplicationDocument;
use App\Models\Scholar;
use App\Models\ScholarComplianceSubmission;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScholarController extends Controller
{
    /**
     * List the current scholars.
     */
    public function index(Request $request): JsonResponse
    {
        $this->syncApprovedApplicationsAsScholars();

        $currentUser = $request->user();
        $search = trim((string) $request->query('search', ''));
        $riskLabel = $request->query('riskLabel');
        $renewalStatus = $request->query('renewalStatus');

        $scholars = Scholar::query()
            ->with(['complianceSubmissions' => fn ($query) => $query->latest('submitted_at')->latest()])
            ->when($currentUser?->isStudent(), fn ($query) => $query->where('user_id', $currentUser->id))
            ->when($currentUser?->isOfficer() && ! $currentUser?->isSuperAdmin(), function ($query) use ($currentUser): void {
                $programIds = $this->assignedProgramIds($currentUser);

                $programIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('scholarship_program_id', $programIds);
            })
            ->when($riskLabel !== null && $riskLabel !== '', fn ($query) => $query->where('risk_label', $riskLabel))
            ->when($renewalStatus !== null && $renewalStatus !== '', fn ($query) => $query->where('renewal_status', $renewalStatus))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($nestedQuery) use ($search): void {
                    $nestedQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('program', 'like', "%{$search}%")
                        ->orWhere('scholar_id', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->get();

        return response()->json([
            'scholars' => ScholarResource::collection($scholars),
        ]);
    }

    /**
     * Show one scholar.
     */
    public function show(Scholar $scholar): JsonResponse
    {
        abort_unless($this->canAccessScholar(request()->user(), $scholar), 403);

        return response()->json([
            'scholar' => new ScholarResource($scholar->loadMissing(['complianceSubmissions' => fn ($query) => $query->latest('submitted_at')->latest()])),
        ]);
    }

    /**
     * Update a scholar's compliance record.
     */
    public function updateCompliance(UpdateScholarComplianceRequest $request, Scholar $scholar): JsonResponse
    {
        abort_unless($this->canAccessScholar($request->user(), $scholar), 403);

        $validated = $request->validated();
        $original = $scholar->only([
            'compliance_status',
            'renewal_status',
            'scholarship_status',
            'recommended_action',
            'risk_label',
        ]);
        $complianceStatus = $this->normalizeComplianceStatus($validated['complianceStatus']);
        $renewalStatus = $validated['renewalStatus']
            ?? $this->mapRenewalStatus($validated['renewalEligibility'] ?? null, $scholar->renewal_status);
        $scholarshipStatus = $validated['scholarshipStatus'] ?? $this->scholarshipStatusForRenewal($renewalStatus, $scholar->scholarship_status);
        $riskLevel = $this->normalizeRiskLevel($validated['riskLevel'] ?? $this->riskLabelForCompliance($complianceStatus));
        $officerNotes = $validated['officerNotes'] ?? $validated['recommendedAction'] ?? $this->recommendedActionForCompliance($complianceStatus);

        $updatedSubmissions = $this->updateRequirementStatuses(
            $this->latestComplianceSubmissions($scholar),
            $validated['coeStatus'] ?? null,
            $validated['corStatus'] ?? null,
            $validated['gradesStatus'] ?? null,
        );

        $scholar->update([
            'compliance_status' => $complianceStatus,
            'renewal_status' => $renewalStatus,
            'scholarship_status' => $scholarshipStatus,
            'recommended_action' => $officerNotes,
            'risk_label' => $riskLevel,
            'risk_reason' => $this->riskReasonForCompliance($complianceStatus, $scholar),
            'compliance_rate' => $this->complianceRateForStatus($complianceStatus),
            'submissions' => $updatedSubmissions,
        ]);

        $this->latestOrCreateComplianceSubmission($scholar)->update([
            'status' => $complianceStatus,
            'coe_status' => $this->submissionStatusFromList($updatedSubmissions, 'coe') ?? 'Missing',
            'cor_status' => $this->submissionStatusFromList($updatedSubmissions, 'cor') ?? 'Missing',
            'grades_status' => $this->submissionStatusFromList($updatedSubmissions, 'encoded-grades') ?? 'Missing',
            'gpa' => $scholar->gpa,
            'submissions' => $updatedSubmissions,
            'grades' => $this->gradesFromSubmissions($updatedSubmissions),
            'officer_notes' => $officerNotes,
            'reviewed_at' => now(),
        ]);

        $scholar->refresh()->loadMissing(['complianceSubmissions' => fn ($query) => $query->latest('submitted_at')->latest()]);
        $this->notifyScholarOfComplianceUpdate($scholar, $original, $officerNotes);

        return response()->json([
            'scholar' => new ScholarResource($scholar->loadMissing(['complianceSubmissions' => fn ($query) => $query->latest('submitted_at')->latest()])),
        ]);
    }

    /**
     * Save the active scholar's semester grade submission.
     */
    public function submitSemesterRequirements(Request $request, Scholar $scholar): JsonResponse
    {
        $currentUser = $request->user();

        abort_unless($this->canAccessScholar($currentUser, $scholar), 403);

        $validated = $request->validate([
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.code' => ['nullable', 'string', 'max:255'],
            'grades.*.subjectCode' => ['nullable', 'string', 'max:255'],
            'grades.*.name' => ['nullable', 'string', 'max:255'],
            'grades.*.subjectName' => ['nullable', 'string', 'max:255'],
            'grades.*.units' => ['nullable', 'numeric', 'min:0'],
            'grades.*.grade' => ['nullable', 'numeric', 'min:0'],
            'gpa' => ['nullable', 'numeric', 'min:0'],
            'semesterSubmissionStatus' => ['nullable', 'string', 'max:255'],
        ]);

        $grades = $this->normalizeGradeRows($validated['grades']);
        $average = $validated['gpa'] ?? $this->computeAverage($grades);
        $submittedAt = now();
        $submissions = $this->buildComplianceSubmissionEntries(
            $scholar,
            $grades,
            $average,
            $validated['semesterSubmissionStatus'] ?? 'Submitted',
            $submittedAt->toISOString(),
        );

        ScholarComplianceSubmission::create([
            'scholar_id' => $scholar->id,
            'scholarship_application_id' => $scholar->scholarship_application_id,
            'semester' => $scholar->semester,
            'academic_year' => $scholar->academic_year,
            'status' => 'Under Review',
            'coe_status' => $this->submissionStatusFromList($submissions, 'coe') ?? 'Submitted',
            'cor_status' => $this->submissionStatusFromList($submissions, 'cor') ?? 'Submitted',
            'grades_status' => $validated['semesterSubmissionStatus'] ?? 'Submitted',
            'gpa' => $average,
            'submissions' => $submissions,
            'grades' => $grades,
            'submitted_at' => $submittedAt,
        ]);

        $scholar->update([
            'gpa' => $average ?: $scholar->gpa,
            'compliance_status' => 'Under Review',
            'renewal_status' => 'Pending Review',
            'scholarship_status' => $scholar->scholarship_status ?: 'Active Scholar',
            'recommended_action' => 'Semester requirements submitted for officer review.',
            'risk_label' => 'Medium Risk',
            'risk_reason' => 'Semester requirements are awaiting officer validation.',
            'compliance_rate' => 70,
            'submissions' => $submissions,
        ]);

        $this->notifyOfficersOfSemesterSubmission($scholar->refresh());

        return response()->json([
            'scholar' => new ScholarResource($scholar->refresh()->loadMissing(['complianceSubmissions' => fn ($query) => $query->latest('submitted_at')->latest()])),
        ]);
    }

    /**
     * Start a fresh semester requirement cycle for every active scholar.
     */
    public function requireSemesterRequirementsForAll(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $requestedAt = now()->toISOString();
        $scholars = Scholar::query()
            ->whereIn('scholarship_status', ['Active Scholar', 'Active', 'Pending Renewal', 'Under Renewal Review'])
            ->when($request->user()?->isOfficer() && ! $request->user()?->isSuperAdmin(), function ($query) use ($request): void {
                $programIds = $this->assignedProgramIds($request->user());

                $programIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('scholarship_program_id', $programIds);
            })
            ->get();

        $scholars->each(function (Scholar $scholar) use ($requestedAt): void {
            $scholar->update([
                'compliance_status' => 'Pending Compliance',
                'renewal_status' => 'Pending Renewal',
                'scholarship_status' => 'Active Scholar',
                'recommended_action' => 'Submit the new semester COE, COR, and encoded grades.',
                'risk_label' => 'Medium Risk',
                'risk_reason' => 'New semester requirements have not been submitted yet.',
                'compliance_rate' => 0,
                'submissions' => $this->resetSemesterRequirementSubmissions($scholar->submissions ?? [], $requestedAt),
            ]);

            ScholarshipNotification::create([
                'user_id' => $scholar->user_id,
                'role' => null,
                'type' => 'task',
                'title' => 'Semester Requirements Required',
                'message' => 'Please submit your new COE, COR, and encoded grades for the current semester.',
                'notified_at' => now(),
                'payload' => [
                    'scholarId' => $scholar->id,
                    'requiredAt' => $requestedAt,
                    'requirements' => ['COE', 'COR', 'Encoded Grades'],
                ],
            ]);
        });

        return response()->json([
            'message' => 'Semester requirements requested for active scholars.',
            'scholars' => ScholarResource::collection($scholars->fresh()->load(['complianceSubmissions' => fn ($query) => $query->latest('submitted_at')->latest()])),
        ]);
    }

    /**
     * Send a requirement request to a scholar.
     */
    public function sendRequirementRequest(StoreRequirementRequest $request, Scholar $scholar): JsonResponse
    {
        abort_unless($this->canAccessScholar($request->user(), $scholar), 403);

        $validated = $request->validated();
        $requirements = $validated['requirements'];
        $message = $validated['message'] ?? 'Please submit the requested requirements.';

        ScholarshipNotification::create([
            'user_id' => $scholar->user_id,
            'role' => $scholar->user?->role ?? 'student',
            'type' => 'task',
            'title' => 'Requirement Request',
            'message' => $message.' Requirements: '.implode(', ', $requirements),
            'notified_at' => now(),
            'payload' => [
                'scholarId' => $scholar->id,
                'requirements' => $requirements,
            ],
        ]);

        return response()->json([
            'message' => 'Requirement request sent.',
        ], 201);
    }

    /**
     * Map compliance decisions to renewal status values.
     */
    private function mapRenewalStatus(?string $renewalEligibility, ?string $currentStatus = null): string
    {
        return match ($renewalEligibility) {
            'Eligible for Renewal' => 'Pending Renewal',
            'Under Evaluation' => 'Under Renewal Review',
            'Needs Review' => 'Pending Renewal',
            'Renewal Denied' => 'Suspended',
            default => $this->normalizeRenewalStatus($currentStatus ?: 'Active Scholar'),
        };
    }

    /**
     * Normalize old and new compliance labels.
     */
    private function normalizeComplianceStatus(string $complianceStatus): string
    {
        return match ($complianceStatus) {
            'Complete' => 'Compliant',
            'Pending Review' => 'Under Review',
            'Pending Compliance', 'Missing Requirements' => 'Incomplete',
            'Not Yet Submitted' => 'Pending Compliance',
            default => $complianceStatus,
        };
    }

    /**
     * Normalize old and new renewal labels.
     */
    private function normalizeRenewalStatus(string $renewalStatus): string
    {
        return match ($renewalStatus) {
            'Active' => 'Active Scholar',
            'Renewal Pending' => 'Pending Renewal',
            'Under Evaluation', 'Under Review' => 'Under Renewal Review',
            default => $renewalStatus,
        };
    }

    /**
     * Determine the scholar status that should be visible to monitoring.
     */
    private function scholarshipStatusForRenewal(string $renewalStatus, ?string $currentStatus): string
    {
        if (in_array($renewalStatus, ['Probation', 'Suspended'], true)) {
            return $renewalStatus;
        }

        return $this->normalizeRenewalStatus($currentStatus ?: 'Active Scholar');
    }

    /**
     * Normalize old risk labels to the monitoring labels.
     */
    private function normalizeRiskLevel(string $riskLevel): string
    {
        return match ($riskLevel) {
            'Stable' => 'Low Risk',
            'Borderline' => 'Medium Risk',
            'At Risk', 'Critical' => 'High Risk',
            default => $riskLevel,
        };
    }

    /**
     * Map compliance status to a risk label.
     */
    private function riskLabelForCompliance(string $complianceStatus): string
    {
        return match ($complianceStatus) {
            'Compliant' => 'Low Risk',
            'Under Review', 'Late Submission', 'Incomplete' => 'Medium Risk',
            'Non-Compliant' => 'High Risk',
            default => 'Medium Risk',
        };
    }

    /**
     * Map compliance status to a risk explanation.
     */
    private function riskReasonForCompliance(string $complianceStatus, Scholar $scholar): string
    {
        return match ($complianceStatus) {
            'Non-Compliant' => 'Scholar is not compliant with the current monitoring cycle.',
            'Incomplete' => 'One or more required documents are still outstanding.',
            'Late Submission' => 'Scholar submitted requirements after the expected deadline.',
            'Under Review' => 'Semester requirements are awaiting officer validation.',
            default => 'Scholar remains in good standing.',
        };
    }

    /**
     * Map compliance status to a completion percentage.
     */
    private function complianceRateForStatus(string $complianceStatus): int
    {
        return match ($complianceStatus) {
            'Compliant' => 100,
            'Under Review' => 70,
            'Late Submission' => 75,
            'Incomplete' => 55,
            'Non-Compliant' => 30,
            default => 80,
        };
    }

    /**
     * Map compliance status to a recommended action.
     */
    private function recommendedActionForCompliance(string $complianceStatus): string
    {
        return match ($complianceStatus) {
            'Compliant' => 'Continue normal scholarship monitoring.',
            'Under Review' => 'Review the submitted semester requirements.',
            'Late Submission' => 'Monitor the scholar for repeated late submissions.',
            'Incomplete' => 'Request the missing requirements from the scholar.',
            'Non-Compliant' => 'Recommend suspension or escalation for scholarship review.',
            default => 'Monitor the scholar closely.',
        };
    }

    /**
     * Notify a scholar when an officer updates compliance or semester validation.
     *
     * @param array<string, mixed> $original
     */
    private function notifyScholarOfComplianceUpdate(Scholar $scholar, array $original, ?string $officerNotes): void
    {
        $hasChanged = collect([
            'compliance_status',
            'renewal_status',
            'scholarship_status',
            'recommended_action',
            'risk_label',
        ])->contains(fn (string $field): bool => ($original[$field] ?? null) !== $scholar->{$field});

        if (! $hasChanged) {
            return;
        }

        $semester = $scholar->semester ?: 'current';
        $message = "Your {$semester} semester compliance status is now {$scholar->compliance_status}.";

        if ($scholar->renewal_status !== null && $scholar->renewal_status !== '') {
            $message .= " Renewal status: {$scholar->renewal_status}.";
        }

        if ($officerNotes !== null && trim($officerNotes) !== '') {
            $message .= ' Officer notes: '.trim($officerNotes);
        }

        ScholarshipNotification::create([
            'user_id' => $scholar->user_id,
            'role' => null,
            'type' => $this->notificationTypeForCompliance($scholar->compliance_status),
            'title' => 'Semester Compliance Updated',
            'message' => $message,
            'notified_at' => now(),
            'payload' => [
                'scholarId' => $scholar->id,
                'complianceStatus' => $scholar->compliance_status,
                'renewalStatus' => $scholar->renewal_status,
                'scholarshipStatus' => $scholar->scholarship_status,
            ],
        ]);
    }

    /**
     * Notify officers that an active scholar submitted semester requirements.
     */
    private function notifyOfficersOfSemesterSubmission(Scholar $scholar): void
    {
        $semester = $scholar->semester ?: 'current semester';

        ScholarshipNotification::create([
            'user_id' => null,
            'role' => 'officer',
            'type' => 'task',
            'title' => 'Semester Requirements Submitted',
            'message' => "{$scholar->name} submitted COE, COR, and encoded grades for {$semester}.",
            'notified_at' => now(),
            'payload' => [
                'scholarId' => $scholar->id,
                'programId' => $scholar->scholarship_program_id,
                'applicationId' => $scholar->scholarship_application_id,
            ],
        ]);
    }

    /**
     * Map compliance status to the notification style used by the UI.
     */
    private function notificationTypeForCompliance(string $complianceStatus): string
    {
        return match ($complianceStatus) {
            'Compliant' => 'success',
            'Incomplete', 'Late Submission', 'Under Review' => 'task',
            'Non-Compliant' => 'warning',
            default => 'status',
        };
    }

    /**
     * Create scholar records for approved applications that were approved before
     * the scholar sync included the Approved status.
     */
    private function syncApprovedApplicationsAsScholars(): void
    {
        ScholarshipApplication::query()
            ->with(['applicant', 'program', 'documents'])
            ->whereIn('status', $this->activeApplicationStatuses())
            ->whereDoesntHave('scholarRecord')
            ->get()
            ->each(fn (ScholarshipApplication $application): Scholar => $this->createScholarFromApplication($application));
    }

    /**
     * Return application statuses that should have scholar records.
     *
     * @return array<int, string>
     */
    private function activeApplicationStatuses(): array
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
     * Build a scholar record from an approved scholarship application.
     */
    private function createScholarFromApplication(ScholarshipApplication $application): Scholar
    {
        $applicant = $application->applicant;
        $program = $application->program;

        return Scholar::create([
            'user_id' => $applicant?->id,
            'scholarship_program_id' => $program?->id,
            'scholarship_application_id' => $application->id,
            'scholar_id' => 'SCH-'.str_pad((string) $application->id, 6, '0', STR_PAD_LEFT),
            'name' => $applicant?->name ?? 'Unknown scholar',
            'avatar' => $applicant?->avatar,
            'program' => $program?->name ?? 'Unknown program',
            'course' => $applicant?->course,
            'year_level' => $applicant?->year_level,
            'school' => $applicant?->school_name ?: $applicant?->campus,
            'gender' => $applicant?->gender,
            'birthdate' => $applicant?->birth_date,
            'address' => $applicant?->address,
            'contact_number' => $applicant?->contact_number,
            'email' => $applicant?->email,
            'gpa' => $applicant?->gpa,
            'enrollment_status' => $applicant?->enrollment_status,
            'academic_year' => $applicant?->academic_year,
            'semester' => $applicant?->semester,
            'scholarship_status' => 'Active Scholar',
            'renewal_status' => $this->renewalStatusForApplication($application->status),
            'date_approved' => now(),
            'duration' => '1 Academic Year',
            'compliance_status' => $this->complianceStatusForApplication($application->status),
            'compliance_rate' => 100,
            'risk_label' => $this->riskLabelForApplication($application->status),
            'risk_reason' => $this->riskReasonForApplicationStatus($application->status),
            'recommended_action' => 'Continue compliance monitoring.',
            'submissions' => $this->buildSubmissionsFromApplication($application),
        ]);
    }

    /**
     * Build scholar submissions from application documents.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSubmissionsFromApplication(ScholarshipApplication $application): array
    {
        $documents = $application->documents->keyBy('name');

        return collect($application->program?->requirements ?? [])
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
     * Map approved application statuses to initial scholar compliance.
     */
    private function complianceStatusForApplication(string $status): string
    {
        return match ($status) {
            'Rejected', 'Terminated', 'Suspended' => 'Non-Compliant',
            'For Revision', 'Needs Revision', 'Submitted', 'Resubmitted', 'Under Review', 'Under Renewal Review' => 'Incomplete',
            'Renewal Pending', 'Pending Renewal', 'Probation' => 'Late Submission',
            default => 'Compliant',
        };
    }

    /**
     * Map approved application statuses to initial scholar renewal status.
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
     * Map approved application statuses to an initial risk label.
     */
    private function riskLabelForApplication(string $status): string
    {
        return match ($status) {
            'Rejected', 'Terminated', 'Suspended' => 'High Risk',
            'Probation' => 'Medium Risk',
            'For Revision', 'Needs Revision' => 'At Risk',
            'Submitted', 'Resubmitted', 'Under Review', 'Renewal Pending', 'Pending Renewal', 'Under Renewal Review' => 'Medium Risk',
            default => 'Low Risk',
        };
    }

    /**
     * Explain the initial scholar risk label.
     */
    private function riskReasonForApplicationStatus(string $status): string
    {
        return match ($status) {
            'Rejected', 'Terminated' => 'Application was not approved.',
            'For Revision', 'Needs Revision' => 'Requirements are still incomplete.',
            'Under Review' => 'Application is under active review.',
            'Renewal Pending' => 'Scholar is waiting for renewal verification.',
            default => 'Scholarship record is in a healthy state.',
        };
    }

    /**
     * Normalize submitted grade rows into one predictable JSON shape.
     *
     * @param array<int, array<string, mixed>> $grades
     * @return array<int, array<string, mixed>>
     */
    private function normalizeGradeRows(array $grades): array
    {
        return collect($grades)
            ->filter(fn (array $grade): bool => ($grade['code'] ?? $grade['subjectCode'] ?? $grade['name'] ?? $grade['subjectName'] ?? $grade['grade'] ?? '') !== '')
            ->values()
            ->map(fn (array $grade, int $index): array => [
                'id' => $grade['id'] ?? $index,
                'code' => $grade['code'] ?? $grade['subjectCode'] ?? '',
                'subjectCode' => $grade['subjectCode'] ?? $grade['code'] ?? '',
                'name' => $grade['name'] ?? $grade['subjectName'] ?? '',
                'subjectName' => $grade['subjectName'] ?? $grade['name'] ?? '',
                'units' => (float) ($grade['units'] ?? 0),
                'grade' => (float) ($grade['grade'] ?? 0),
            ])
            ->all();
    }

    /**
     * Compute a weighted average using units.
     *
     * @param array<int, array<string, mixed>> $grades
     */
    private function computeAverage(array $grades): ?float
    {
        $totalUnits = collect($grades)->sum(fn (array $grade): float => (float) ($grade['units'] ?? 0));

        if ($totalUnits <= 0) {
            return null;
        }

        $weightedTotal = collect($grades)->sum(fn (array $grade): float => (float) ($grade['grade'] ?? 0) * (float) ($grade['units'] ?? 0));

        return round($weightedTotal / $totalUnits, 2);
    }

    /**
     * Store encoded grades as a semester submission entry.
     *
     * @param array<int, array<string, mixed>> $submissions
     * @param array<int, array<string, mixed>> $grades
     * @return array<int, array<string, mixed>>
     */
    private function saveEncodedGradesSubmission(array $submissions, array $grades, ?float $average, string $status): array
    {
        $submittedAt = now()->toISOString();
        $updated = $this->updateSubmissionByKey($submissions, 'encoded-grades', [
            'key' => 'encoded-grades',
            'requirement' => 'Encoded Grades',
            'name' => 'Encoded Grades',
            'status' => $status,
            'submittedAt' => $submittedAt,
            'grades' => $grades,
            'gradeRows' => $grades,
            'encodedGrades' => $grades,
            'gpa' => $average,
        ]);

        return $this->updateRequirementStatuses($updated, 'Submitted', 'Submitted', $status);
    }

    /**
     * Update persisted requirement statuses without losing uploaded-grade rows.
     *
     * @param array<int, array<string, mixed>> $submissions
     * @return array<int, array<string, mixed>>
     */
    private function updateRequirementStatuses(array $submissions, ?string $coeStatus, ?string $corStatus, ?string $gradesStatus): array
    {
        $updated = $submissions;

        if ($coeStatus !== null) {
            $updated = $this->updateSubmissionByKey($updated, 'coe', [
                'key' => 'coe',
                'requirement' => 'Certificate of Enrollment / COE',
                'name' => 'Certificate of Enrollment / COE',
                'status' => $coeStatus,
                'submittedAt' => now()->toISOString(),
            ]);
        }

        if ($corStatus !== null) {
            $updated = $this->updateSubmissionByKey($updated, 'cor', [
                'key' => 'cor',
                'requirement' => 'Certificate of Registration / COR',
                'name' => 'Certificate of Registration / COR',
                'status' => $corStatus,
                'submittedAt' => now()->toISOString(),
            ]);
        }

        if ($gradesStatus !== null) {
            $updated = $this->updateSubmissionByKey($updated, 'encoded-grades', [
                'key' => 'encoded-grades',
                'requirement' => 'Encoded Grades',
                'name' => 'Encoded Grades',
                'status' => $gradesStatus,
                'submittedAt' => now()->toISOString(),
            ]);
        }

        return array_values($updated);
    }

    /**
     * Reset semester-cycle requirements without carrying over old uploads or grades.
     *
     * @param array<int, array<string, mixed>> $submissions
     * @return array<int, array<string, mixed>>
     */
    private function resetSemesterRequirementSubmissions(array $submissions, string $requestedAt): array
    {
        $updated = collect($submissions)
            ->reject(function (array $submission): bool {
                $key = $submission['key'] ?? str($submission['requirement'] ?? $submission['name'] ?? '')->lower()->slug('-')->toString();

                return in_array($key, ['coe', 'cor', 'encoded-grades'], true);
            })
            ->values()
            ->all();

        return [
            ...$updated,
            [
                'key' => 'coe',
                'requirement' => 'Certificate of Enrollment / COE',
                'name' => 'Certificate of Enrollment / COE',
                'status' => 'Missing',
                'requestedAt' => $requestedAt,
            ],
            [
                'key' => 'cor',
                'requirement' => 'Certificate of Registration / COR',
                'name' => 'Certificate of Registration / COR',
                'status' => 'Missing',
                'requestedAt' => $requestedAt,
            ],
            [
                'key' => 'encoded-grades',
                'requirement' => 'Encoded Grades',
                'name' => 'Encoded Grades',
                'status' => 'Missing',
                'requestedAt' => $requestedAt,
                'grades' => [],
                'gradeRows' => [],
                'encodedGrades' => [],
            ],
        ];
    }

    /**
     * Build a fresh compliance row payload from current uploads and encoded grades.
     *
     * @param array<int, array<string, mixed>> $grades
     * @return array<int, array<string, mixed>>
     */
    private function buildComplianceSubmissionEntries(Scholar $scholar, array $grades, ?float $average, string $status, string $submittedAt): array
    {
        $documents = ApplicationDocument::query()
            ->where('scholarship_application_id', $scholar->scholarship_application_id)
            ->whereIn('name', ['Certificate of Enrollment / COE', 'Certificate of Registration / COR'])
            ->get()
            ->keyBy('name');

        $coe = $documents->get('Certificate of Enrollment / COE');
        $cor = $documents->get('Certificate of Registration / COR');

        return [
            $this->documentSubmissionEntry('coe', 'Certificate of Enrollment / COE', $coe, $submittedAt),
            $this->documentSubmissionEntry('cor', 'Certificate of Registration / COR', $cor, $submittedAt),
            [
                'key' => 'encoded-grades',
                'requirement' => 'Encoded Grades',
                'name' => 'Encoded Grades',
                'status' => $status,
                'submittedAt' => $submittedAt,
                'grades' => $grades,
                'gradeRows' => $grades,
                'encodedGrades' => $grades,
                'gpa' => $average,
            ],
        ];
    }

    /**
     * Convert a document upload to the submission shape consumed by the frontend.
     *
     * @return array<string, mixed>
     */
    private function documentSubmissionEntry(string $key, string $name, ?ApplicationDocument $document, string $submittedAt): array
    {
        return array_filter([
            'key' => $key,
            'requirement' => $name,
            'name' => $name,
            'status' => $document?->status ?? 'Missing',
            'submittedAt' => $document?->uploaded_at?->toISOString() ?? $submittedAt,
            'document' => $document ? [
                'id' => $document->id,
                'applicationId' => $document->scholarship_application_id,
                'name' => $document->name,
                'fileName' => $document->name,
                'type' => $document->type,
                'path' => $document->path,
                'fileUrl' => $document->path ? route('documents.file', $document->id) : null,
                'status' => $document->status,
                'remarks' => $document->remarks,
                'uploadedAt' => $document->uploaded_at?->toISOString(),
            ] : null,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * Return latest row submissions, falling back to the legacy scholar JSON.
     *
     * @return array<int, array<string, mixed>>
     */
    private function latestComplianceSubmissions(Scholar $scholar): array
    {
        $latestSubmission = $scholar->complianceSubmissions()->latest('submitted_at')->latest()->first();

        return $latestSubmission?->submissions ?? $scholar->submissions ?? [];
    }

    /**
     * Get the latest compliance row or create one when an officer reviews before a student row exists.
     */
    private function latestOrCreateComplianceSubmission(Scholar $scholar): ScholarComplianceSubmission
    {
        $latestSubmission = $scholar->complianceSubmissions()->latest('submitted_at')->latest()->first();

        if ($latestSubmission !== null) {
            return $latestSubmission;
        }

        return ScholarComplianceSubmission::create([
            'scholar_id' => $scholar->id,
            'scholarship_application_id' => $scholar->scholarship_application_id,
            'semester' => $scholar->semester,
            'academic_year' => $scholar->academic_year,
            'status' => $scholar->compliance_status ?: 'Pending Compliance',
            'coe_status' => $this->submissionStatusFromList($scholar->submissions ?? [], 'coe') ?? 'Missing',
            'cor_status' => $this->submissionStatusFromList($scholar->submissions ?? [], 'cor') ?? 'Missing',
            'grades_status' => $this->submissionStatusFromList($scholar->submissions ?? [], 'encoded-grades') ?? 'Missing',
            'gpa' => $scholar->gpa,
            'submissions' => $scholar->submissions ?? [],
            'grades' => $this->gradesFromSubmissions($scholar->submissions ?? []),
            'submitted_at' => now(),
        ]);
    }

    /**
     * Check whether a user can access one scholar record.
     */
    private function canAccessScholar($user, Scholar $scholar): bool
    {
        if ($user === null) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isOfficer()) {
            return in_array((int) $scholar->scholarship_program_id, $this->assignedProgramIds($user), true);
        }

        return $scholar->user_id === $user->id;
    }

    /**
     * Return assigned scholarship program ids for an officer.
     *
     * @return array<int, int>
     */
    private function assignedProgramIds($user): array
    {
        return array_values(array_map('intval', $user->assigned_program_ids ?? []));
    }

    /**
     * Return a requirement status from a normalized submission list.
     *
     * @param array<int, array<string, mixed>> $submissions
     */
    private function submissionStatusFromList(array $submissions, string $key): ?string
    {
        foreach ($submissions as $submission) {
            $submissionKey = $submission['key'] ?? str($submission['requirement'] ?? $submission['name'] ?? '')->lower()->slug('-')->toString();

            if ($submissionKey === $key) {
                return $submission['status'] ?? null;
            }
        }

        return null;
    }

    /**
     * Pull encoded grade rows from a submission list.
     *
     * @param array<int, array<string, mixed>> $submissions
     * @return array<int, array<string, mixed>>
     */
    private function gradesFromSubmissions(array $submissions): array
    {
        foreach ($submissions as $submission) {
            $submissionKey = $submission['key'] ?? str($submission['requirement'] ?? $submission['name'] ?? '')->lower()->slug('-')->toString();

            if ($submissionKey === 'encoded-grades') {
                return $submission['grades'] ?? $submission['gradeRows'] ?? $submission['encodedGrades'] ?? [];
            }
        }

        return [];
    }
    /**
     * Merge a submission entry by key or requirement name.
     *
     * @param array<int, array<string, mixed>> $submissions
     * @param array<string, mixed> $entry
     * @return array<int, array<string, mixed>>
     */
    private function updateSubmissionByKey(array $submissions, string $key, array $entry): array
    {
        foreach ($submissions as $index => $submission) {
            $submissionKey = $submission['key'] ?? str($submission['requirement'] ?? $submission['name'] ?? '')->lower()->slug('-')->toString();

            if ($submissionKey === $key) {
                $submissions[$index] = array_merge($submission, array_filter($entry, fn ($value): bool => $value !== null));

                return $submissions;
            }
        }

        $submissions[] = array_filter($entry, fn ($value): bool => $value !== null);

        return $submissions;
    }
}
