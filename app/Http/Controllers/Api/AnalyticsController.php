<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scholar;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use App\Services\ApplicantForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(private readonly ApplicantForecastService $applicantForecastService) {}

    /**
     * Return scholarship analytics and report rows.
     */
    public function summary(Request $request): JsonResponse
    {
        $programIds = $this->visibleProgramIds($request);
        $programs = ScholarshipProgram::query()
            ->when($programIds !== null, fn ($query) => $query->whereIn('id', $programIds))
            ->get();
        $applications = ScholarshipApplication::query()
            ->when($programIds !== null, fn ($query) => $query->whereIn('scholarship_program_id', $programIds))
            ->get();
        $scholars = Scholar::query()
            ->when($programIds !== null, fn ($query) => $query->whereIn('scholarship_program_id', $programIds))
            ->get();

        return response()->json([
            'analytics' => [
                'refreshedAt' => now()->format('M d, Y h:i A'),
                'trend' => $this->buildTrend($applications, $scholars),
                'programDistribution' => $this->buildProgramDistribution($programs, $scholars),
                'riskSummary' => $this->buildRiskSummary($scholars),
                'complianceSummary' => $this->buildComplianceSummary($scholars),
                'forecasts' => $this->buildForecasts($scholars),
                'applicantForecast' => $this->applicantForecastService->build(
                    $programIds,
                    null,
                    (string) $request->query('dateRange', 'Last 6 months'),
                ),
            ],
            'reports' => $this->buildReports($programs, $applications),
        ]);
    }

    /**
     * Return applicant volume forecasts for officer planning.
     */
    public function applicantForecast(Request $request): JsonResponse
    {
        $programIds = $this->visibleProgramIds($request);
        $programId = $request->integer('programId') ?: null;

        if ($programId !== null && $programIds !== null && ! in_array($programId, $programIds, true)) {
            abort(403);
        }

        return response()->json([
            'applicantForecast' => $this->applicantForecastService->build(
                $programIds,
                $programId,
                (string) $request->query('dateRange', 'Last 6 months'),
            ),
        ]);
    }

    /**
     * Build a month-by-month trend payload.
     *
     * @param  iterable<ScholarshipApplication>  $applications
     * @param  iterable<Scholar>  $scholars
     * @return array<int, array<string, mixed>>
     */
    private function buildTrend(iterable $applications, iterable $scholars): array
    {
        $approvedStatuses = ['Approved', 'Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewed'];

        return collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($applications, $approvedStatuses): array {
                $date = now()->subMonths($monthsAgo);
                $monthApplications = collect($applications)->filter(function (ScholarshipApplication $application) use ($date): bool {
                    return $application->created_at?->format('Y-m') === $date->format('Y-m');
                });
                $monthApproved = $monthApplications->whereIn('status', $approvedStatuses)->count();

                return [
                    'month' => $date->format('M'),
                    'applications' => $monthApplications->count(),
                    'approved' => $monthApproved,
                    'compliance' => $monthApplications->count() > 0
                        ? (int) round(($monthApproved / max($monthApplications->count(), 1)) * 100)
                        : 0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build program distribution records.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildProgramDistribution(iterable $programs, iterable $scholars): array
    {
        return collect($programs)
            ->map(function (ScholarshipProgram $program) use ($scholars): array {
                $activeScholarCount = collect($scholars)
                    ->where('scholarship_program_id', $program->id)
                    ->count();

                return [
                    'label' => $program->name,
                    'programId' => $program->id,
                    'count' => $activeScholarCount,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build the current risk summary.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRiskSummary(iterable $scholars): array
    {
        return collect(['Stable', 'Borderline', 'At Risk', 'Critical'])
            ->map(function (string $label) use ($scholars): array {
                $count = collect($scholars)->where('risk_label', $label)->count();

                return [
                    'label' => $label,
                    'count' => $count,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build the current compliance summary.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildComplianceSummary(iterable $scholars): array
    {
        return collect(['Complete', 'Pending Review', 'Missing Requirements', 'Non-Compliant'])
            ->map(function (string $label) use ($scholars): array {
                $count = collect($scholars)->where('compliance_status', $label)->count();

                return [
                    'label' => $label,
                    'count' => $count,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build the forecast percentages for the risk chart.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildForecasts(iterable $scholars): array
    {
        $totalScholars = max(collect($scholars)->count(), 1);

        return collect(['Stable', 'Borderline', 'At Risk', 'Critical'])
            ->map(function (string $label) use ($scholars, $totalScholars): array {
                $count = collect($scholars)->where('risk_label', $label)->count();

                return [
                    'label' => $label,
                    'value' => (int) round(($count / $totalScholars) * 100),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build report rows for the UI table.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildReports(iterable $programs, iterable $applications): array
    {
        return collect($programs)
            ->values()
            ->map(function (ScholarshipProgram $program, int $index) use ($applications): array {
                $applicationCount = collect($applications)
                    ->where('scholarship_program_id', $program->id)
                    ->count();

                return [
                    'id' => $index + 1,
                    'programId' => $program->id,
                    'name' => $program->name.' Monitoring Report',
                    'type' => $index % 2 === 0 ? 'PDF' : 'CSV',
                    'generatedAt' => now()->format('M d, Y h:i A'),
                    'owner' => 'Scholarship Administration',
                    'applicationCount' => $applicationCount,
                ];
            })
            ->all();
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

        if ($user->isSuperAdmin()) {
            return null;
        }

        if ($user->isOfficer()) {
            return array_values(array_map('intval', $user->assigned_program_ids ?? []));
        }

        return [];
    }
}
