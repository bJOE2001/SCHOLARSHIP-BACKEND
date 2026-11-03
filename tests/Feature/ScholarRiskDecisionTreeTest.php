<?php

namespace Tests\Feature;

use App\Models\Scholar;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScholarRiskDecisionTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_risk_detection_uses_decision_tree_classifier(): void
    {
        $officer = User::factory()->headOfficer()->create();
        $program = ScholarshipProgram::factory()->published()->create([
            'name' => 'Engineering Excellence Program',
            'maintaining_grade' => 85.0,
        ]);

        $studentAtRisk = User::factory()->create(['name' => 'Failing Student']);
        $studentBorderline = User::factory()->create(['name' => 'Borderline Student']);
        $studentStable = User::factory()->create(['name' => 'Stable Student']);

        Scholar::factory()->create([
            'user_id' => $studentAtRisk->id,
            'scholarship_program_id' => $program->id,
            'name' => 'Failing Student',
            'gpa' => 74.0,
            'compliance_rate' => 40,
            'compliance_status' => 'Non-Compliant',
        ]);

        Scholar::factory()->create([
            'user_id' => $studentBorderline->id,
            'scholarship_program_id' => $program->id,
            'name' => 'Borderline Student',
            'gpa' => 86.0,
            'compliance_rate' => 85,
            'compliance_status' => 'Compliant',
        ]);

        Scholar::factory()->create([
            'user_id' => $studentStable->id,
            'scholarship_program_id' => $program->id,
            'name' => 'Stable Student',
            'gpa' => 93.0,
            'compliance_rate' => 100,
            'compliance_status' => 'Compliant',
        ]);

        Sanctum::actingAs($officer);

        // 1. ML Enabled (Decision Tree)
        $response = $this->getJson('/api/risk-detection')
            ->assertOk()
            ->assertJsonStructure([
                'riskRows' => [
                    '*' => [
                        'id',
                        'name',
                        'program',
                        'gpa',
                        'maintainingGrade',
                        'riskLabel',
                        'gradeMargin',
                        'decisionRule',
                        'probabilities',
                        'riskReason',
                        'recommendedAction',
                        'modelMethod',
                    ],
                ],
                'riskSummary',
                'forecasts',
            ]);

        $rows = collect($response->json('riskRows'));

        $atRiskRow = $rows->firstWhere('name', 'Failing Student');
        $this->assertNotNull($atRiskRow);
        $this->assertEquals('At Risk', $atRiskRow['riskLabel']);
        $this->assertEquals(-11.0, (float) $atRiskRow['gradeMargin']);
        $this->assertStringContainsString('Decision Tree', $atRiskRow['modelMethod']);
        $this->assertNotNull($atRiskRow['decisionRule']);

        $borderlineRow = $rows->firstWhere('name', 'Borderline Student');
        $this->assertNotNull($borderlineRow);
        $this->assertEquals('Borderline', $borderlineRow['riskLabel']);

        $stableRow = $rows->firstWhere('name', 'Stable Student');
        $this->assertNotNull($stableRow);
        $this->assertEquals('Stable', $stableRow['riskLabel']);

        // 2. ML Disabled - Fallback Driver Check
        config(['services.forecasting.enabled' => false]);

        $fallbackResponse = $this->getJson('/api/risk-detection')->assertOk();
        $fallbackRows = collect($fallbackResponse->json('riskRows'));
        $fallbackAtRisk = $fallbackRows->firstWhere('name', 'Failing Student');
        $this->assertEquals('At Risk', $fallbackAtRisk['riskLabel']);
        $this->assertEquals('Heuristic Grade Rule', $fallbackAtRisk['modelMethod']);
    }
}
