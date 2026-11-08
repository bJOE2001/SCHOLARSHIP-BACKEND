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
     * Evaluate scholar risk for reports or legacy callers.
     *
     * @param  Collection<int, Scholar>  $scholars
     * @param  Collection<int, float>  $maintainingGrades
     * @return array<int, array<string, mixed>>
     */
    public function evaluateBatch(Collection $scholars, Collection $maintainingGrades): array
    {
        return $this->evaluate($scholars, $maintainingGrades)['riskRows'];
    }

    /**
     * Evaluate scholar risk using the Decision Tree classifier strictly requiring the ML service.
     *
     * @param  Collection<int, Scholar>  $scholars
     * @param  Collection<int, float>  $maintainingGrades
     * @return array<string, mixed>
     */
    public function evaluate(Collection $scholars, Collection $maintainingGrades): array
    {
        if ($scholars->isEmpty()) {
            return [
                'available' => true,
                'status' => 'online',
                'method' => 'Decision Tree Classifier',
                'riskRows' => [],
                'riskSummary' => [],
                'forecasts' => [],
            ];
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

        if ($mlResults === null || ($mlResults['status'] ?? '') !== 'success') {
            return [
                'available' => false,
                'status' => 'offline',
                'message' => 'ML Risk Detection service is offline. Risk evaluation strictly relies on the Machine Learning Decision Tree microservice.',
                'method' => 'ML Service Offline',
                'riskRows' => [],
                'riskSummary' => [
                    ['label' => 'Stable', 'count' => 0],
                    ['label' => 'Borderline', 'count' => 0],
                    ['label' => 'At Risk', 'count' => 0],
                ],
                'forecasts' => [
                    ['label' => 'Stable', 'value' => 0],
                    ['label' => 'Borderline', 'value' => 0],
                    ['label' => 'At Risk', 'value' => 0],
                ],
            ];
        }

        $resultsById = collect($mlResults['results'] ?? [])->keyBy('id');

        $riskRows = $scholars->map(function (Scholar $scholar) use ($maintainingGrades, $resultsById): array {
            $maintainingGrade = (float) ($maintainingGrades->get($scholar->scholarship_program_id) ?? 85.0);
            $mlEvaluation = $resultsById->get($scholar->id);

            $riskLabel = (string) ($mlEvaluation['riskLabel'] ?? 'At Risk');

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
                'riskReason' => $mlEvaluation['riskReason'] ?? "GPA is below the maintaining grade of {$maintainingGrade}.",
                'recommendedAction' => $scholar->recommended_action ?: ($mlEvaluation['recommendedAction'] ?? 'Continue normal monitoring'),
                'modelMethod' => 'Decision Tree Classifier',
                'updatedAt' => $scholar->updated_at?->toISOString(),
            ];
        })->values()->all();

        $riskSummary = collect(['Stable', 'Borderline', 'At Risk'])
            ->map(fn (string $label): array => [
                'label' => $label,
                'count' => collect($riskRows)->where('riskLabel', $label)->count(),
            ])
            ->all();

        $totalScholars = max(count($riskRows), 1);
        $forecasts = collect($riskSummary)
            ->map(fn (array $item): array => [
                'label' => $item['label'],
                'value' => (int) round(($item['count'] / $totalScholars) * 100),
            ])
            ->all();

        return [
            'available' => true,
            'status' => 'online',
            'method' => $mlResults['method'] ?? 'Decision Tree Classifier',
            'riskRows' => $riskRows,
            'riskSummary' => $riskSummary,
            'forecasts' => $forecasts,
        ];
    }

    /**
     * Run ML evaluation via HTTP or subprocess when explicitly configured.
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
            $serviceUrl = (string) config('services.forecasting.service_url', 'http://127.0.0.1:8001');
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
                // When HTTP is offline or unreachable, do not silently fallback in auto mode
            }
        }

        if ($driver === 'process') {
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
}
