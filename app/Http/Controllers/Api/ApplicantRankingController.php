<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScholarshipApplication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicantRankingController extends Controller
{
    /**
     * Return backend-ranked applicant rows.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isOfficer(), 403);

        $programIds = $this->visibleProgramIds($request);
        $applications = ScholarshipApplication::query()
            ->with(['applicant', 'program'])
            ->where('status', '<>', 'Draft')
            ->when($programIds !== null, fn (Builder $query) => $query->whereIn('scholarship_program_id', $programIds))
            ->get()
            ->sortByDesc(fn (ScholarshipApplication $application): int => $this->scoreForStatus($application->status, (int) $application->score))
            ->values();

        $rows = $applications
            ->map(function (ScholarshipApplication $application, int $index): array {
                return [
                    'id' => $application->id,
                    'rank' => $index + 1,
                    'applicationNo' => $application->application_no,
                    'applicantId' => $application->applicant_id,
                    'applicantName' => $application->applicant?->name ?? 'Unknown applicant',
                    'programId' => $application->scholarship_program_id,
                    'scholarshipProgramId' => $application->scholarship_program_id,
                    'programName' => $application->program?->name ?? 'Unknown program',
                    'gpa' => $application->applicant?->gpa ?? 'Pending',
                    'score' => $this->scoreForStatus($application->status, (int) $application->score),
                    'status' => $application->status,
                    'eligibility' => $this->eligibilityForApplication($application),
                    'recommendation' => $this->recommendationForStatus($application->status),
                    'submittedAt' => $application->submitted_at?->toISOString(),
                    'updatedAt' => $application->updated_at?->toISOString(),
                ];
            })
            ->all();

        return response()->json([
            'rankings' => $rows,
        ]);
    }

    /**
     * Return scoped program ids, or null for head officer/all-access users.
     *
     * @return array<int, int>|null
     */
    private function visibleProgramIds(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || $user->isSuperAdmin()) {
            return null;
        }

        if ($user->isOfficer()) {
            return array_values(array_map('intval', $user->assigned_program_ids ?? []));
        }

        return [];
    }

    private function eligibilityForApplication(ScholarshipApplication $application): string
    {
        if (in_array($application->status, ['Rejected', 'Ineligible'], true) || ($application->missing_requirements ?? []) !== []) {
            return 'Pending Validation';
        }

        return 'Eligible';
    }

    private function recommendationForStatus(string $status): string
    {
        return match ($status) {
            'Eligible', 'Shortlisted', 'Approved', 'Active Scholar' => 'Recommended',
            'Rejected' => 'Not Recommended',
            'Application Review Approved' => 'Pending',
            default => 'Pending',
        };
    }

    private function scoreForStatus(string $status, int $currentScore): int
    {
        return match ($status) {
            'Eligible' => max($currentScore, 80),
            'Shortlisted', 'Approved', 'Active Scholar' => max($currentScore, 90),
            default => $currentScore,
        };
    }
}
