<?php

namespace Tests\Feature;

use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RandomForestForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecast_uses_random_forest_with_graceful_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23 09:00:00'));

        try {
            $program = ScholarshipProgram::factory()->published()->create([
                'name' => 'Data Science Scholars',
                'slots' => 50,
            ]);
            $officer = User::factory()->headOfficer()->create();

            $applicationIndex = 1;
            $createBatch = function (int $count, string $submittedAt) use ($program, &$applicationIndex): void {
                for ($i = 0; $i < $count; $i++) {
                    $student = User::factory()->create();
                    ScholarshipApplication::factory()->submitted()->create([
                        'scholarship_program_id' => $program->id,
                        'applicant_id' => $student->id,
                        'application_no' => sprintf('APP-RF-%03d', $applicationIndex++),
                        'submitted_at' => Carbon::parse($submittedAt),
                        'created_at' => Carbon::parse($submittedAt),
                    ]);
                }
            };

            $createBatch(3, '2026-02-10 10:00:00');
            $createBatch(5, '2026-03-10 10:00:00');
            $createBatch(8, '2026-04-10 10:00:00');
            $createBatch(4, '2026-05-10 10:00:00');

            Sanctum::actingAs($officer);

            // 1. With ML Enabled (Default)
            $response = $this->getJson("/api/analytics/applicant-forecast?dateRange=Last%203%20months&programId={$program->id}")
                ->assertOk()
                ->assertJsonStructure([
                    'applicantForecast' => [
                        'method',
                        'lookbackMonths',
                        'summary' => [
                            'currentMonthApplicants',
                            'predictedApplicantsThisMonth',
                            'predictedApplicantsNextMonth',
                            'averageMonthlyApplicants',
                            'confidence',
                            'featureImportances',
                            'history',
                        ],
                        'programs',
                    ],
                ]);

            $method = (string) $response->json('applicantForecast.method');
            $this->assertStringContainsString('Random Forest', $method);
            $this->assertEquals(4, $response->json('applicantForecast.summary.currentMonthApplicants'));
            $this->assertGreaterThan(0, $response->json('applicantForecast.summary.predictedApplicantsThisMonth'));

            // 2. With ML Disabled - Verify Offline Fallback Contract
            config(['services.forecasting.enabled' => false]);

            $offlineResponse = $this->getJson("/api/analytics/applicant-forecast?dateRange=Last%203%20months&programId={$program->id}")
                ->assertOk()
                ->assertJsonPath('applicantForecast.available', false)
                ->assertJsonPath('applicantForecast.status', 'offline')
                ->assertJsonPath('applicantForecast.method', 'ML Service Offline')
                ->assertJsonPath('applicantForecast.summary.available', false)
                ->assertJsonPath('applicantForecast.summary.currentMonthApplicants', 4)
                ->assertJsonPath('applicantForecast.summary.predictedApplicantsThisMonth', null)
                ->assertJsonPath('applicantForecast.summary.predictedApplicantsNextMonth', null)
                ->assertJsonPath('applicantForecast.summary.confidence', 'Unavailable');
        } finally {
            Carbon::setTestNow();
        }
    }
}
