<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Scholar;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /**
     * Return scholarship analytics and report rows.
     */
    public function summary(Request $request): JsonResponse
    {
        $programs = ScholarshipProgram::query()->get();
        $applications = ScholarshipApplication::query()->get();
        $scholars = Scholar::query()->get();

        return response()->json([
            'analytics' => [
                'refreshedAt' => now()->format('M d, Y h:i A'),
                'trend' => $this->buildTrend($applications, $scholars),
                'programDistribution' => $this->buildProgramDistribution($programs, $applications),
                'riskSummary' => $this->buildRiskSummary($scholars),
                'complianceSummary' => $this->buildComplianceSummary($scholars),
                'forecasts' => $this->buildForecasts($scholars),
            ],
            'reports' => $this->buildReports($programs, $applications),
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
        $approvedStatuses = ['Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewed'];

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
    private function buildProgramDistribution(iterable $programs, iterable $applications): array
    {
        return collect($programs)
            ->map(function (ScholarshipProgram $program) use ($applications): array {
                $applicationCount = collect($applications)
                    ->where('scholarship_program_id', $program->id)
                    ->count();

                return [
                    'label' => $program->name,
                    'programId' => $program->id,
                    'count' => $applicationCount,
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
}
