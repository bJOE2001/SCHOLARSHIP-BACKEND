<?php

namespace App\Services;

use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ApplicantForecastService
{
    /**
     * Build applicant forecasts for all visible programs or one selected program.
     *
     * @param  array<int, int>|null  $visibleProgramIds
     * @return array<string, mixed>
     */
    public function build(?array $visibleProgramIds = null, ?int $programId = null, string $dateRange = 'Last 6 months'): array
    {
        $lookbackMonths = $this->lookbackMonths($dateRange);
        $currentMonth = now()->copy()->startOfMonth();
        $months = collect(range($lookbackMonths, 0))
            ->map(fn (int $monthsAgo): Carbon => $currentMonth->copy()->subMonths($monthsAgo))
            ->values();

        $programIds = $this->programIds($visibleProgramIds, $programId);
        $programs = ScholarshipProgram::query()
            ->select(['id', 'name'])
            ->when($programIds !== null, fn ($query) => $query->whereIn('id', $programIds))
            ->orderBy('name')
            ->get();
        $applications = $this->applications($programIds, $currentMonth->copy()->subMonths($lookbackMonths));
        $summary = $this->forecastForScope($applications, null, $months);
        $programForecasts = $programs
            ->map(fn (ScholarshipProgram $program): array => $this->forecastForScope($applications, $program, $months))
            ->values();

        return [
            'generatedAt' => now()->toISOString(),
            'method' => 'Weighted moving average with current-month pace',
            'lookbackMonths' => $lookbackMonths,
            'summary' => $summary,
            'programs' => $programForecasts,
        ];
    }

    /**
     * @param  array<int, int>|null  $visibleProgramIds
     * @return array<int, int>|null
     */
    private function programIds(?array $visibleProgramIds, ?int $programId): ?array
    {
        if ($programId !== null) {
            return [$programId];
        }

        if ($visibleProgramIds === null) {
            return null;
        }

        return array_values(array_unique(array_map(
            static fn (mixed $visibleProgramId): int => (int) $visibleProgramId,
            $visibleProgramIds,
        )));
    }

    /**
     * @param  array<int, int>|null  $programIds
     * @return Collection<int, ScholarshipApplication>
     */
    private function applications(?array $programIds, Carbon $startMonth): Collection
    {
        return ScholarshipApplication::query()
            ->select(['id', 'scholarship_program_id', 'submitted_at', 'created_at'])
            ->when($programIds !== null, fn ($query) => $query->whereIn('scholarship_program_id', $programIds))
            ->where(function ($query) use ($startMonth): void {
                $query
                    ->where('submitted_at', '>=', $startMonth)
                    ->orWhere(function ($fallbackQuery) use ($startMonth): void {
                        $fallbackQuery
                            ->whereNull('submitted_at')
                            ->where('created_at', '>=', $startMonth);
                    });
            })
            ->get();
    }

    /**
     * @param  Collection<int, ScholarshipApplication>  $applications
     * @param  Collection<int, Carbon>  $months
     * @return array<string, mixed>
     */
    private function forecastForScope(Collection $applications, ?ScholarshipProgram $program, Collection $months): array
    {
        $scopedApplications = $program instanceof ScholarshipProgram
            ? $applications->where('scholarship_program_id', $program->id)->values()
            : $applications;
        $history = $months
            ->map(fn (Carbon $month): array => [
                'month' => $month->format('M Y'),
                'monthKey' => $month->format('Y-m'),
                'applicants' => $this->countForMonth($scopedApplications, $month),
            ])
            ->values();
        $completeMonthCounts = $history
            ->slice(0, max($history->count() - 1, 0))
            ->pluck('applicants')
            ->map(static fn (mixed $count): int => (int) $count)
            ->values();
        $currentMonthApplicants = (int) ($history->last()['applicants'] ?? 0);
        $historicalAverage = $completeMonthCounts->isEmpty()
            ? 0.0
            : $completeMonthCounts->average();
        $weightedAverage = $this->weightedAverage($completeMonthCounts);
        $currentPace = $this->currentMonthPace($currentMonthApplicants);
        $predictedApplicantsThisMonth = $currentMonthApplicants > 0
            ? max($currentMonthApplicants, (int) round(($weightedAverage + $currentPace) / 2))
            : (int) round($weightedAverage);
        $predictedApplicantsNextMonth = (int) round($weightedAverage);
        $previousMonthApplicants = (int) ($completeMonthCounts->last() ?? 0);

        return [
            'programId' => $program?->id,
            'programName' => $program?->name ?? 'All Programs',
            'currentMonthApplicants' => $currentMonthApplicants,
            'predictedApplicantsThisMonth' => $predictedApplicantsThisMonth,
            'predictedApplicantsNextMonth' => $predictedApplicantsNextMonth,
            'averageMonthlyApplicants' => round($historicalAverage, 1),
            'trendDelta' => $predictedApplicantsThisMonth - $previousMonthApplicants,
            'confidence' => $this->confidence($completeMonthCounts),
            'history' => $history->all(),
        ];
    }

    /**
     * @param  Collection<int, ScholarshipApplication>  $applications
     */
    private function countForMonth(Collection $applications, Carbon $month): int
    {
        $monthKey = $month->format('Y-m');

        return $applications
            ->filter(fn (ScholarshipApplication $application): bool => $this->applicationDate($application)?->format('Y-m') === $monthKey)
            ->count();
    }

    private function applicationDate(ScholarshipApplication $application): ?Carbon
    {
        return $application->submitted_at ?? $application->created_at;
    }

    private function currentMonthPace(int $currentMonthApplicants): float
    {
        if ($currentMonthApplicants === 0) {
            return 0.0;
        }

        return ($currentMonthApplicants / max(now()->day, 1)) * now()->daysInMonth;
    }

    /**
     * @param  Collection<int, int>  $completeMonthCounts
     */
    private function weightedAverage(Collection $completeMonthCounts): float
    {
        if ($completeMonthCounts->isEmpty()) {
            return 0.0;
        }

        $weightedTotal = 0;
        $weightTotal = 0;

        $completeMonthCounts->values()->each(function (int $count, int $index) use (&$weightedTotal, &$weightTotal): void {
            $weight = $index + 1;
            $weightedTotal += $count * $weight;
            $weightTotal += $weight;
        });

        return $weightTotal > 0 ? $weightedTotal / $weightTotal : 0.0;
    }

    /**
     * @param  Collection<int, int>  $completeMonthCounts
     */
    private function confidence(Collection $completeMonthCounts): string
    {
        $historicalApplicants = $completeMonthCounts->sum();
        $activeMonths = $completeMonthCounts->filter(static fn (int $count): bool => $count > 0)->count();

        if ($historicalApplicants >= 12 && $activeMonths >= 3) {
            return 'High';
        }

        if ($historicalApplicants >= 4 && $activeMonths >= 2) {
            return 'Medium';
        }

        return 'Low';
    }

    private function lookbackMonths(string $dateRange): int
    {
        return match ($dateRange) {
            'Last 30 days' => 3,
            'Last 3 months' => 3,
            'School year 2026' => 10,
            default => 6,
        };
    }
}
