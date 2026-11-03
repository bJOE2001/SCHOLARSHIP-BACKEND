<?php

namespace App\Services;

use App\Models\Scholar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class ScholarRiskService
{
    /**
     * Evaluate scholar risk using the Decision Tree classifier with graceful fallback.
     *
     * @param  Collection<int, Scholar>  $scholars
     * @param  Collection<int, float>  $maintainingGrades
     * @return array<int, array<string, mixed>>
     */
    public function evaluateBatch(Collection $scholars, Collection $maintainingGrades): array
    {
        if ($scholars->isEmpty()) {
            return [];
        }

        $scholarsPayload = $scholars->map(fn (Scholar $scholar): array => [
            'id' => $scholar->id,
            'scholarId' => $scholar->scholar_id,
            'name' => $scholar->name,
            'program' => $scholar->program,
            'gpa' => (float) $scholar->gpa,
            'maintainingGrade' => (float) ($maintainingGrades->get($scholar->scholarship_program_id) ?? 85.0),
            'complianceRate' => (int) ($scholar->compliance_rate ?? 100),
            'complianceStatus' => (string) ($scholar->compliance_status ?? 'Compliant'),
            'enrollmentStatus' => (string) ($scholar->enrollment_status ?? 'Enrolled'),
        ])->values()->all();

        $mlResults = $this->runMlEvaluation($scholarsPayload);
        $resultsById = collect($mlResults['results'] ?? [])->keyBy('id');

        return $scholars->map(function (Scholar $scholar) use ($maintainingGrades, $resultsById): array {
            $maintainingGrade = (float) ($maintainingGrades->get($scholar->scholarship_program_id) ?? 85.0);
            $mlEvaluation = $resultsById->get($scholar->id);

            if ($mlEvaluation !== null) {
                $riskLabel = (string) ($mlEvaluation['riskLabel'] ?? $this->fallbackRiskLabel($scholar, $maintainingGrade));

                return [
                    'id' => $scholar->id,
                    'scholarId' => $scholar->scholar_id,
                    'userId' => $scholar->user_id,
                    'programId' => $scholar->scholarship_program_id,
                    'name' => $scholar->name,
                    'program' => $scholar->program,
                    'gpa' => $scholar->gpa,
                    'maintainingGrade' => $maintainingGrade,
                    'riskLabel' => $riskLabel,
                    'gradeMargin' => $mlEvaluation['gradeMargin'] ?? round(((float) $scholar->gpa) - $maintainingGrade, 2),
                    'decisionRule' => $mlEvaluation['decisionRule'] ?? null,
                    'probabilities' => $mlEvaluation['probabilities'] ?? null,
                    'riskReason' => $mlEvaluation['riskReason'] ?? $this->fallbackRiskReason($scholar, $riskLabel, $maintainingGrade),
                    'recommendedAction' => $scholar->recommended_action ?: ($mlEvaluation['recommendedAction'] ?? $this->fallbackRecommendedAction($riskLabel)),
                    'modelMethod' => 'Decision Tree Classifier',
                    'updatedAt' => $scholar->updated_at?->toISOString(),
                ];
            }

            $fallbackLabel = $this->fallbackRiskLabel($scholar, $maintainingGrade);

            return [
                'id' => $scholar->id,
                'scholarId' => $scholar->scholar_id,
                'userId' => $scholar->user_id,
                'programId' => $scholar->scholarship_program_id,
                'name' => $scholar->name,
                'program' => $scholar->program,
                'gpa' => $scholar->gpa,
                'maintainingGrade' => $maintainingGrade,
                'riskLabel' => $fallbackLabel,
                'gradeMargin' => round(((float) $scholar->gpa) - $maintainingGrade, 2),
                'decisionRule' => null,
                'probabilities' => null,
                'riskReason' => $this->fallbackRiskReason($scholar, $fallbackLabel, $maintainingGrade),
                'recommendedAction' => $scholar->recommended_action ?: $this->fallbackRecommendedAction($fallbackLabel),
                'modelMethod' => 'Heuristic Grade Rule',
                'updatedAt' => $scholar->updated_at?->toISOString(),
            ];
        })->values()->all();
    }

    /**
     * Run ML evaluation via HTTP or subprocess.
     *
     * @param  array<int, array<string, mixed>>  $scholarsPayload
     * @return array<string, mixed>|null
     */
    private function runMlEvaluation(array $scholarsPayload): ?array
    {
        $isEnabled = (bool) config('services.forecasting.enabled', true);

        if (! $isEnabled) {
            return null;
        }

        $driver = (string) config('services.forecasting.driver', 'auto');

        if ($driver === 'http' || $driver === 'auto') {
            $serviceUrl = (string) config('services.forecasting.service_url', 'http://127.0.0.1:8000');
            try {
                $response = Http::timeout(3)
                    ->connectTimeout(1)
                    ->post(rtrim($serviceUrl, '/').'/risk-detection/evaluate', [
                        'scholars' => $scholarsPayload,
                    ]);

                if ($response->successful()) {
                    $decoded = $response->json();
                    if (is_array($decoded) && ($decoded['status'] ?? '') === 'success') {
                        return $decoded;
                    }
                }
            } catch (Throwable) {
                // Fall back to process
            }
        }

        if ($driver === 'process' || $driver === 'auto') {
            $pythonPath = (string) config('services.forecasting.python_path', 'python');
            $riskScriptPath = (string) config(
                'services.forecasting.risk_script_path',
                base_path('../SCHOLARSHIP-FORECASTING/risk_service.py'),
            );

            if (! file_exists($riskScriptPath)) {
                return null;
            }

            try {
                $jsonInput = json_encode(['scholars' => $scholarsPayload], JSON_THROW_ON_ERROR);
                $processResult = Process::input($jsonInput)
                    ->timeout((int) config('services.forecasting.timeout_seconds', 5))
                    ->run([$pythonPath, $riskScriptPath]);

                if ($processResult->successful()) {
                    $decoded = json_decode(trim($processResult->output()), true);
                    if (is_array($decoded) && ($decoded['status'] ?? '') === 'success') {
                        return $decoded;
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('ScholarRiskService process execution error.', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    private function fallbackRiskLabel(Scholar $scholar, float $maintainingGrade): string
    {
        $gpa = (float) $scholar->gpa;

        if ($gpa <= 0 || $gpa < $maintainingGrade) {
            return 'At Risk';
        }

        return $gpa < ($maintainingGrade + 5) ? 'Borderline' : 'Stable';
    }

    private function fallbackRiskReason(Scholar $scholar, string $riskLabel, float $maintainingGrade): string
    {
        if ((float) $scholar->gpa <= 0) {
            return "No GPA recorded. Maintaining grade is {$maintainingGrade}.";
        }

        return match ($riskLabel) {
            'At Risk' => "GPA is below the maintaining grade of {$maintainingGrade}.",
            'Borderline' => "GPA is close to the maintaining grade of {$maintainingGrade}.",
            default => "GPA meets the maintaining grade of {$maintainingGrade}.",
        };
    }

    private function fallbackRecommendedAction(string $riskLabel): string
    {
        return match ($riskLabel) {
            'At Risk' => 'Schedule advising and monitor the next GPA submission',
            'Borderline' => 'Send reminder and monitor next GPA submission',
            default => 'Continue normal monitoring',
        };
    }
}
