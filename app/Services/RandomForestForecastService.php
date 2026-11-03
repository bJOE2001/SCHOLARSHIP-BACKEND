<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

class RandomForestForecastService
{
    /**
     * Run Random Forest forecasting given the prepared payload.
     *
     * @param  array<string, mixed>  $forecastPayload
     * @return array<string, mixed>|null
     */
    public function forecast(array $forecastPayload): ?array
    {
        $isEnabled = (bool) config('services.forecasting.enabled', true);

        if (! $isEnabled) {
            return null;
        }

        $driver = (string) config('services.forecasting.driver', 'auto');

        if ($driver === 'http' || $driver === 'auto') {
            $httpResult = $this->runViaHttp($forecastPayload);
            if ($httpResult !== null) {
                return $httpResult;
            }
        }

        if ($driver === 'process' || $driver === 'auto') {
            return $this->runViaProcess($forecastPayload);
        }

        return null;
    }

    /**
     * Execute prediction by calling the FastAPI service via HTTP.
     *
     * @param  array<string, mixed>  $forecastPayload
     * @return array<string, mixed>|null
     */
    private function runViaHttp(array $forecastPayload): ?array
    {
        $serviceUrl = (string) config('services.forecasting.service_url', 'http://127.0.0.1:8000');
        $timeoutSeconds = (int) config('services.forecasting.timeout_seconds', 3);

        try {
            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout(1)
                ->post(rtrim($serviceUrl, '/').'/forecast', $forecastPayload);

            if ($response->successful()) {
                $decoded = $response->json();
                if (is_array($decoded) && ($decoded['status'] ?? '') === 'success') {
                    return $decoded;
                }
            }
        } catch (Throwable $exception) {
            Log::debug('RandomForest HTTP service unavailable, falling back to process/heuristic.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Execute prediction by invoking the Python script as a subprocess via standard input.
     *
     * @param  array<string, mixed>  $forecastPayload
     * @return array<string, mixed>|null
     */
    private function runViaProcess(array $forecastPayload): ?array
    {
        $pythonPath = (string) config('services.forecasting.python_path', 'python');
        $scriptPath = (string) config('services.forecasting.script_path', base_path('../SCHOLARSHIP-FORECASTING/forecast_service.py'));
        $timeoutSeconds = (int) config('services.forecasting.timeout_seconds', 5);

        if (! file_exists($scriptPath)) {
            Log::warning('RandomForest forecasting script not found at expected path.', [
                'path' => $scriptPath,
            ]);

            return null;
        }

        try {
            $jsonPayload = json_encode($forecastPayload, JSON_THROW_ON_ERROR);
            $processResult = Process::input($jsonPayload)
                ->timeout($timeoutSeconds)
                ->run([$pythonPath, $scriptPath]);

            if ($processResult->successful()) {
                $output = trim($processResult->output());
                $decoded = json_decode($output, true);

                if (is_array($decoded) && ($decoded['status'] ?? '') === 'success') {
                    return $decoded;
                }
            } else {
                Log::debug('RandomForest process execution exited with non-zero status.', [
                    'exitCode' => $processResult->exitCode(),
                    'error' => $processResult->errorOutput(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('RandomForest process execution exception encountered.', [
                'message' => $exception->getMessage(),
            ]);
        }

        return null;
    }
}
