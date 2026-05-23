<?php

namespace Tests\Feature;

use App\Models\Scholar;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GrantDistributionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_head_officer_can_create_grant_batch_with_scheduled_beneficiaries(): void
    {
        $headOfficer = User::factory()->headOfficer()->create();
        $program = ScholarshipProgram::factory()->published()->create([
            'name' => 'City Scholars Grant',
        ]);
        $firstScholar = $this->createScholar($program, 'First Scholar');
        $secondScholar = $this->createScholar($program, 'Second Scholar');

        Sanctum::actingAs($headOfficer);

        $response = $this->postJson('/api/grant-distribution/batches', $this->batchPayload($program, [
            ['id' => $firstScholar->id, 'onHold' => false],
            ['id' => $secondScholar->id, 'onHold' => false],
        ]));

        $response->assertCreated();
        $response->assertJsonPath('batch.title', 'May 2026 Grant Release');
        $response->assertJsonPath('batch.programId', $program->id);
        $response->assertJsonPath('batch.beneficiaries.0.scholarName', 'First Scholar');
        $response->assertJsonPath('batch.beneficiaries.0.assignedClaimDate', '2026-05-25');
        $response->assertJsonPath('batch.beneficiaries.1.assignedClaimDate', '2026-05-26');
        $response->assertJsonPath('batch.beneficiaries.1.claimStatus', 'For Claiming');

        $this->assertDatabaseHas('grant_batches', [
            'scholarship_program_id' => $program->id,
            'title' => 'May 2026 Grant Release',
            'status' => 'Open',
        ]);
        $this->assertDatabaseCount('grant_beneficiaries', 2);
    }

    public function test_officer_can_only_manage_assigned_program_grant_batches(): void
    {
        $assignedProgram = ScholarshipProgram::factory()->published()->create();
        $unassignedProgram = ScholarshipProgram::factory()->published()->create();
        $officer = User::factory()->officer()->create([
            'assigned_program_ids' => [$assignedProgram->id],
        ]);
        $assignedScholar = $this->createScholar($assignedProgram, 'Assigned Scholar');
        $unassignedScholar = $this->createScholar($unassignedProgram, 'Hidden Scholar');

        Sanctum::actingAs($officer);

        $this->postJson('/api/grant-distribution/batches', $this->batchPayload($assignedProgram, [
            ['id' => $assignedScholar->id],
        ]))->assertCreated();

        $this->postJson('/api/grant-distribution/batches', $this->batchPayload($unassignedProgram, [
            ['id' => $unassignedScholar->id],
        ]))->assertForbidden();

        $this->getJson('/api/grant-distribution')
            ->assertOk()
            ->assertJsonCount(1, 'batches')
            ->assertJsonPath('batches.0.programId', $assignedProgram->id);
    }

    public function test_grant_batch_can_notify_announce_release_and_close(): void
    {
        $headOfficer = User::factory()->headOfficer()->create([
            'name' => 'Grant Officer',
        ]);
        $program = ScholarshipProgram::factory()->published()->create([
            'name' => 'Release Program',
        ]);
        $firstScholar = $this->createScholar($program, 'Claiming Scholar');
        $secondScholar = $this->createScholar($program, 'Unclaimed Scholar');

        Sanctum::actingAs($headOfficer);

        $batchResponse = $this->postJson('/api/grant-distribution/batches', $this->batchPayload($program, [
            ['id' => $firstScholar->id],
            ['id' => $secondScholar->id],
        ]));
        $batchId = $batchResponse->json('batch.id');
        $firstBeneficiaryId = $batchResponse->json('batch.beneficiaries.0.id');

        $this->postJson("/api/grant-distribution/batches/{$batchId}/notify")
            ->assertOk()
            ->assertJsonPath('beneficiaries.0.notifiedAt', fn (?string $notifiedAt): bool => $notifiedAt !== null);

        $this->postJson("/api/grant-distribution/batches/{$batchId}/announcements")
            ->assertCreated()
            ->assertJsonPath('announcement.programName', 'Release Program')
            ->assertJsonPath('announcement.totalBeneficiaries', 2);

        $this->getJson('/api/grant-distribution/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'announcements')
            ->assertJsonPath('announcements.0.batch.beneficiaries.0.scholarName', 'Claiming Scholar');

        $this->postJson("/api/grant-distribution/batches/{$batchId}/beneficiaries/{$firstBeneficiaryId}/release", [
            'referenceNumber' => 'REL-2026-0001',
            'claimMethod' => 'Cash',
            'remarks' => 'Released at window 1.',
        ])
            ->assertOk()
            ->assertJsonPath('beneficiary.claimStatus', 'Claimed')
            ->assertJsonPath('beneficiary.releasedBy', 'Grant Officer');

        $this->patchJson("/api/grant-distribution/batches/{$batchId}/close")
            ->assertOk()
            ->assertJsonPath('batch.status', 'Closed')
            ->assertJsonPath('batch.beneficiaries.1.claimStatus', 'Not Claimed');

        $this->assertDatabaseHas('grant_beneficiaries', [
            'id' => $firstBeneficiaryId,
            'claim_status' => 'Claimed',
            'reference_number' => 'REL-2026-0001',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $scholars
     * @return array<string, mixed>
     */
    private function batchPayload(ScholarshipProgram $program, array $scholars): array
    {
        return [
            'title' => 'May 2026 Grant Release',
            'programId' => $program->id,
            'programName' => $program->name,
            'semester' => '1st Semester',
            'schoolYear' => '2025-2026',
            'amount' => 7500,
            'claimingStartDate' => '2026-05-25',
            'claimingEndDate' => '2026-05-30',
            'venue' => 'Scholarship Programs Office',
            'dailyLimit' => 1,
            'remarks' => 'Bring a valid ID.',
            'status' => 'Open',
            'scholars' => $scholars,
        ];
    }

    private function createScholar(ScholarshipProgram $program, string $name): Scholar
    {
        $student = User::factory()->create([
            'name' => $name,
            'semester' => '1st Semester',
        ]);

        return Scholar::factory()->create([
            'user_id' => $student->id,
            'scholarship_program_id' => $program->id,
            'name' => $name,
            'program' => $program->name,
            'semester' => '1st Semester',
            'scholarship_status' => 'Active Scholar',
            'renewal_status' => 'Approved',
            'compliance_status' => 'Compliant',
            'compliance_rate' => 100,
            'risk_label' => 'Low Risk',
        ]);
    }
}
