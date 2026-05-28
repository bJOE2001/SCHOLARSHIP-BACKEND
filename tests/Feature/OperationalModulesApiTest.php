<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Scholar;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipNotification;
use App\Models\ScholarshipProgram;
use App\Models\SemesterRequirementDraft;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalModulesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_view_published_announcements(): void
    {
        $program = ScholarshipProgram::factory()->published()->create([
            'name' => 'Public Program',
        ]);
        Announcement::factory()->create([
            'scholarship_program_id' => $program->id,
            'title' => 'Public Renewal Notice',
            'message' => 'Public announcement details.',
            'status' => 'Published',
            'published_at' => now(),
        ]);

        $this->getJson('/api/announcements/public')
            ->assertOk()
            ->assertJsonCount(1, 'announcements')
            ->assertJsonPath('announcements.0.title', 'Public Renewal Notice')
            ->assertJsonPath('announcements.0.programName', 'Public Program');
    }

    public function test_officer_can_publish_announcements_and_save_settings(): void
    {
        $officer = User::factory()->headOfficer()->create();
        $program = ScholarshipProgram::factory()->published()->create([
            'name' => 'Community Scholars',
        ]);

        Sanctum::actingAs($officer);

        $this->postJson('/api/announcements', [
            'programId' => $program->id,
            'title' => 'Renewal Window Open',
            'message' => 'Submit renewal requirements by Friday.',
            'pin' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('announcement.title', 'Renewal Window Open')
            ->assertJsonPath('announcement.programName', 'Community Scholars')
            ->assertJsonPath('announcement.pin', true);

        $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonCount(1, 'announcements')
            ->assertJsonPath('announcements.0.title', 'Renewal Window Open');

        $this->patchJson('/api/settings', [
            'settings' => [
                'emailAlerts' => false,
                'riskAlerts' => true,
                'defaultRange' => 'Last 30 days',
                'tableDensity' => 'Compact',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('settings.settings.emailAlerts', false)
            ->assertJsonPath('settings.settings.tableDensity', 'Compact');

        $this->assertDatabaseHas(Announcement::class, [
            'title' => 'Renewal Window Open',
            'scholarship_program_id' => $program->id,
        ]);
        $this->assertDatabaseHas(ScholarshipNotification::class, [
            'role' => 'student',
            'title' => 'Renewal Window Open',
        ]);
        $this->assertDatabaseHas(UserSetting::class, [
            'user_id' => $officer->id,
        ]);
    }

    public function test_officer_can_delete_owned_announcement(): void
    {
        $officer = User::factory()->headOfficer()->create();
        $program = ScholarshipProgram::factory()->published()->create([
            'name' => 'Deleted Notice Program',
        ]);
        $announcement = Announcement::factory()->create([
            'scholarship_program_id' => $program->id,
            'created_by_id' => $officer->id,
            'title' => 'Delete Me',
            'message' => 'This announcement should be removed.',
            'status' => 'Published',
            'published_at' => now(),
        ]);
        ScholarshipNotification::factory()->create([
            'role' => 'student',
            'title' => 'Delete Me',
            'payload' => [
                'announcementId' => $announcement->id,
                'programId' => $program->id,
            ],
        ]);

        Sanctum::actingAs($officer);

        $this->deleteJson("/api/announcements/{$announcement->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Announcement deleted.');

        $this->assertDatabaseMissing(Announcement::class, [
            'id' => $announcement->id,
        ]);
        $this->assertDatabaseMissing(ScholarshipNotification::class, [
            'title' => 'Delete Me',
        ]);
    }

    public function test_officer_can_load_rankings_risk_detection_and_report_exports(): void
    {
        $program = ScholarshipProgram::factory()->published()->create([
            'name' => 'Merit Program',
            'maintaining_grade' => 75,
        ]);
        $officer = User::factory()->officer()->create([
            'assigned_program_ids' => [$program->id],
        ]);
        $student = User::factory()->create([
            'name' => 'Ranked Student',
            'gpa' => 92,
        ]);
        ScholarshipApplication::factory()->submitted()->create([
            'scholarship_program_id' => $program->id,
            'applicant_id' => $student->id,
            'application_no' => 'APP-RANK-001',
            'score' => 88,
            'missing_requirements' => [],
        ]);
        Scholar::factory()->create([
            'user_id' => $student->id,
            'scholarship_program_id' => $program->id,
            'name' => 'Ranked Student',
            'program' => 'Merit Program',
            'gpa' => 72,
            'risk_label' => 'High Risk',
            'compliance_status' => 'Non-Compliant',
            'compliance_rate' => 25,
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/rankings')
            ->assertOk()
            ->assertJsonPath('rankings.0.applicationNo', 'APP-RANK-001')
            ->assertJsonPath('rankings.0.rank', 1)
            ->assertJsonPath('rankings.0.programName', 'Merit Program');

        $this->getJson('/api/risk-detection')
            ->assertOk()
            ->assertJsonPath('riskRows.0.name', 'Ranked Student')
            ->assertJsonPath('riskRows.0.riskLabel', 'At Risk')
            ->assertJsonPath('riskSummary.2.label', 'At Risk')
            ->assertJsonPath('riskSummary.2.count', 1);

        $response = $this->get('/api/reports/export?type=CSV');

        $response->assertOk();
        $this->assertStringContainsString('Merit Program Monitoring Report', $response->streamedContent());
    }

    public function test_approving_one_application_removes_students_other_pending_applications(): void
    {
        $approvedProgram = ScholarshipProgram::factory()->published()->create([
            'name' => 'Approved Program',
        ]);
        $otherProgram = ScholarshipProgram::factory()->published()->create([
            'name' => 'Other Program',
        ]);
        $student = User::factory()->create([
            'name' => 'Multi Applicant',
        ]);
        $officer = User::factory()->officer()->create([
            'assigned_program_ids' => [$approvedProgram->id, $otherProgram->id],
        ]);
        $application = ScholarshipApplication::factory()->underReview()->create([
            'scholarship_program_id' => $approvedProgram->id,
            'applicant_id' => $student->id,
        ]);
        $otherSubmittedApplication = ScholarshipApplication::factory()->submitted()->create([
            'scholarship_program_id' => $otherProgram->id,
            'applicant_id' => $student->id,
        ]);
        $otherRejectedApplication = ScholarshipApplication::factory()->rejected()->create([
            'scholarship_program_id' => $otherProgram->id,
            'applicant_id' => $student->id,
        ]);

        Sanctum::actingAs($officer);

        $this->patchJson("/api/applications/{$application->id}/status", [
            'status' => 'Approved',
            'remarks' => 'Approved for scholarship.',
        ])
            ->assertOk()
            ->assertJsonPath('application.status', 'Approved')
            ->assertJsonPath('removedApplicationIds.0', $otherSubmittedApplication->id);

        $this->assertDatabaseHas(ScholarshipApplication::class, [
            'id' => $application->id,
            'status' => 'Approved',
        ]);
        $this->assertDatabaseMissing(ScholarshipApplication::class, [
            'id' => $otherSubmittedApplication->id,
        ]);
        $this->assertDatabaseHas(ScholarshipApplication::class, [
            'id' => $otherRejectedApplication->id,
            'status' => 'Rejected',
        ]);
        $this->assertDatabaseHas(Scholar::class, [
            'scholarship_application_id' => $application->id,
            'user_id' => $student->id,
            'scholarship_status' => 'Active Scholar',
        ]);
    }

    public function test_officer_can_view_applicant_forecast_from_application_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-23 09:00:00'));

        try {
            $program = ScholarshipProgram::factory()->published()->create([
                'name' => 'Forecast Program',
            ]);
            $otherProgram = ScholarshipProgram::factory()->published()->create([
                'name' => 'Other Program',
            ]);
            $officer = User::factory()->officer()->create([
                'assigned_program_ids' => [$program->id],
            ]);
            $applicationIndex = 1;
            $createApplications = function (ScholarshipProgram $targetProgram, int $count, string $submittedAt) use (&$applicationIndex): void {
                for ($index = 0; $index < $count; $index++) {
                    $student = User::factory()->create([
                        'email' => sprintf('forecast.student.%03d@example.com', $applicationIndex),
                    ]);

                    ScholarshipApplication::factory()->submitted()->create([
                        'scholarship_program_id' => $targetProgram->id,
                        'applicant_id' => $student->id,
                        'application_no' => sprintf('APP-FORECAST-%03d', $applicationIndex),
                        'submitted_at' => Carbon::parse($submittedAt),
                        'created_at' => Carbon::parse($submittedAt),
                    ]);

                    $applicationIndex++;
                }
            };

            $createApplications($program, 2, '2026-02-12 10:00:00');
            $createApplications($program, 4, '2026-03-12 10:00:00');
            $createApplications($program, 6, '2026-04-12 10:00:00');
            $createApplications($program, 3, '2026-05-12 10:00:00');
            $createApplications($otherProgram, 5, '2026-05-12 10:00:00');

            Sanctum::actingAs($officer);

            $this->getJson("/api/analytics/applicant-forecast?dateRange=Last%203%20months&programId={$program->id}")
                ->assertOk()
                ->assertJsonPath('applicantForecast.lookbackMonths', 3)
                ->assertJsonPath('applicantForecast.summary.currentMonthApplicants', 3)
                ->assertJsonPath('applicantForecast.summary.predictedApplicantsThisMonth', 4)
                ->assertJsonPath('applicantForecast.summary.predictedApplicantsNextMonth', 5)
                ->assertJsonPath('applicantForecast.summary.averageMonthlyApplicants', 4)
                ->assertJsonPath('applicantForecast.summary.confidence', 'High')
                ->assertJsonPath('applicantForecast.programs.0.programName', 'Forecast Program')
                ->assertJsonCount(4, 'applicantForecast.summary.history');

            $this->getJson('/api/analytics?dateRange=Last%203%20months')
                ->assertOk()
                ->assertJsonPath('analytics.applicantForecast.summary.currentMonthApplicants', 3);

            $this->getJson("/api/analytics/applicant-forecast?programId={$otherProgram->id}")
                ->assertForbidden();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_student_can_save_semester_draft_and_update_profile(): void
    {
        $student = User::factory()->create([
            'name' => 'Original Student',
            'course' => 'BSIT',
        ]);
        $program = ScholarshipProgram::factory()->published()->create();
        $application = ScholarshipApplication::factory()->activeScholar()->create([
            'applicant_id' => $student->id,
            'scholarship_program_id' => $program->id,
        ]);
        $scholar = Scholar::factory()->create([
            'user_id' => $student->id,
            'scholarship_program_id' => $program->id,
            'scholarship_application_id' => $application->id,
            'name' => 'Original Student',
            'course' => 'BSIT',
        ]);

        Sanctum::actingAs($student);

        $this->putJson('/api/semester-requirement-draft', [
            'scholarId' => $scholar->id,
            'applicationId' => $application->id,
            'status' => 'Draft',
            'grades' => [
                [
                    'code' => 'IT 101',
                    'name' => 'Programming 1',
                    'units' => 3,
                    'grade' => 90,
                ],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('draft.status', 'Draft')
            ->assertJsonPath('draft.computedAverage', 90);

        $this->getJson('/api/semester-requirement-draft')
            ->assertOk()
            ->assertJsonPath('draft.grades.0.code', 'IT 101');

        $this->patchJson('/api/auth/profile', [
            'name' => 'Updated Student',
            'course' => 'BSCS',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated Student')
            ->assertJsonPath('user.course', 'BSCS');

        $this->assertDatabaseHas(SemesterRequirementDraft::class, [
            'user_id' => $student->id,
            'scholar_id' => $scholar->id,
        ]);
        $this->assertDatabaseHas(User::class, [
            'id' => $student->id,
            'name' => 'Updated Student',
            'course' => 'BSCS',
        ]);
        $this->assertDatabaseHas(Scholar::class, [
            'id' => $scholar->id,
            'name' => 'Updated Student',
            'course' => 'BSCS',
        ]);
    }
}
