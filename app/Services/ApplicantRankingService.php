<?php

namespace App\Services;

use App\Models\ScholarshipApplication;
use Illuminate\Support\Facades\Http;
use Throwable;

class ApplicantRankingService
{
    /**
     * Compute composite ranking breakdown for an application via ML API or heuristic fallback.
     *
     * @return array<string, mixed>
     */
    public function scoreApplication(ScholarshipApplication $application): array
    {
        $gpa = (float) ($application->applicant?->gpa ?? 0.0);
        $maintainingGrade = (float) ($application->program?->maintaining_grade ?? 85.0);
        $missingRequirements = (array) ($application->missing_requirements ?? []);
        $annualIncome = (float) ($application->applicant?->annual_income ?? 0.0);

        $serviceUrl = (string) config('services.forecasting.service_url', 'http://127.0.0.1:8000');
        $driver = (string) config('services.forecasting.driver', 'auto');
        $isEnabled = (bool) config('services.forecasting.enabled', true);

        if ($isEnabled && ($driver === 'http' || $driver === 'auto')) {
            try {
                $response = Http::timeout(2)
                    ->connectTimeout(1)
                    ->post(rtrim($serviceUrl, '/').'/ranking/evaluate', [
                        'applicants' => [[
                            'id' => $application->id,
                            'gpa' => $gpa,
                            'maintainingGrade' => $maintainingGrade,
                            'income' => $annualIncome,
                            'missingRequirements' => $missingRequirements,
                            'isPriorityCourse' => $this->isPriorityCourse($application),
                        ]],
                    ]);

                if ($response->successful()) {
                    $result = $response->json('results.0');
                    if (is_array($result) && isset($result['compositeScore'])) {
                        return [
                            'compositeScore' => (float) $result['compositeScore'],
                            'meritScore' => (float) $result['meritScore'],
                            'needScore' => (float) $result['needScore'],
                            'completenessScore' => (float) $result['completenessScore'],
                            'priorityScore' => (float) $result['priorityScore'],
                            'priorityCategory' => (string) $result['priorityCategory'],
                        ];
                    }
                }
            } catch (Throwable) {
                // Fall back to local calculation
            }
        }

        $meritScore = $this->calculateMeritScore($gpa, $maintainingGrade);
        $needScore = $this->calculateNeedScore($application);
        $completenessScore = max(0.0, 100.0 - (count($missingRequirements) * 20.0));
        $priorityScore = $this->calculatePriorityScore($application);

        $compositeScore = round(
            (0.40 * $meritScore) + (0.35 * $needScore) + (0.15 * $completenessScore) + (0.10 * $priorityScore),
            1
        );

        $priorityCategory = match (true) {
            $compositeScore >= 88.0 && $completenessScore >= 80.0 => 'High Priority',
            $compositeScore >= 75.0 => 'Standard Priority',
            default => 'Needs Review',
        };

        return [
            'compositeScore' => $compositeScore,
            'meritScore' => round($meritScore, 1),
            'needScore' => round($needScore, 1),
            'completenessScore' => round($completenessScore, 1),
            'priorityScore' => round($priorityScore, 1),
            'priorityCategory' => $priorityCategory,
        ];
    }

    private function calculateMeritScore(float $gpa, float $maintainingGrade): float
    {
        if ($gpa <= 0.0) {
            return 70.0;
        }

        if ($gpa > 10.0) {
            return max(0.0, min(100.0, $gpa));
        }

        return 85.0;
    }

    private function calculateNeedScore(ScholarshipApplication $application): float
    {
        $rawIncome = $application->applicant?->annual_income ?? 0.0;
        $income = (float) $rawIncome;

        if ($income <= 0.0) {
            return 85.0;
        }

        if ($income <= 150000.0) {
            return 100.0;
        }

        if ($income <= 250000.0) {
            return 85.0;
        }

        if ($income <= 400000.0) {
            return 70.0;
        }

        if ($income <= 600000.0) {
            return 50.0;
        }

        return 35.0;
    }

    private function calculatePriorityScore(ScholarshipApplication $application): float
    {
        return $this->isPriorityCourse($application) ? 95.0 : 75.0;
    }

    private function isPriorityCourse(ScholarshipApplication $application): bool
    {
        $course = strtolower((string) ($application->applicant?->course ?? ''));
        $programCategory = strtolower((string) ($application->program?->category ?? ''));

        if (str_contains($programCategory, 'science') || str_contains($programCategory, 'stem')) {
            return str_contains($course, 'bsit') || str_contains($course, 'cs') || str_contains($course, 'eng');
        }

        return false;
    }
}
