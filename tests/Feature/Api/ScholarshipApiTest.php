<?php

namespace Tests\Feature\Api;

use App\Models\ApplicationDocument;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScholarshipApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_login_returns_token_and_user(): void
    {
        $student = User::factory()->create([
            'name' => 'Login Student',
            'email' => 'login.student@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'token',
            'user' => [
                'id',
                'name',
                'email',
                'role',
            ],
        ]);
        $response->assertJsonPath('user.role', 'student');
    }

    public function test_public_program_listing_returns_open_programs(): void
    {
        ScholarshipProgram::create([
            'name' => 'Open Program',
            'provider' => 'ScholarSync Foundation',
            'category' => 'Merit',
            'type' => 'Scholarship',
            'description' => 'Open program description.',
            'eligibility_summary' => 'Open summary.',
            'status' => 'Open',
            'slots' => 10,
            'used_slots' => 2,
            'budget' => 500000,
            'schedule' => [
                'opening' => 'May 01, 2026',
                'deadline' => 'June 01, 2026',
                'screening' => 'June 10, 2026',
                'awarding' => 'June 20, 2026',
            ],
            'eligibility' => ['Currently enrolled'],
            'requirements' => ['Transcript'],
            'scoring_criteria' => ['Grades'],
            'renewal_rules' => ['Keep GPA'],
            'assigned_admin_ids' => [],
            'published_at' => now(),
        ]);

        ScholarshipProgram::create([
            'name' => 'Closed Program',
            'provider' => 'ScholarSync Foundation',
            'category' => 'Need-Based',
            'type' => 'Grant',
            'description' => 'Closed program description.',
            'eligibility_summary' => 'Closed summary.',
            'status' => 'Closed',
            'slots' => 10,
            'used_slots' => 10,
            'budget' => 500000,
            'schedule' => [
                'opening' => 'January 01, 2026',
                'deadline' => 'February 01, 2026',
                'screening' => 'February 10, 2026',
                'awarding' => 'February 20, 2026',
            ],
            'eligibility' => ['Currently enrolled'],
            'requirements' => ['Transcript'],
            'scoring_criteria' => ['Grades'],
            'renewal_rules' => ['Keep GPA'],
            'assigned_admin_ids' => [],
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/programs?published=true');

        $response->assertOk();
        $response->assertJsonCount(1, 'programs');
        $response->assertJsonPath('programs.0.name', 'Open Program');
    }

    public function test_student_can_create_draft_application_and_requirement_documents(): void
    {
        $student = User::factory()->create([
            'name' => 'Draft Student',
            'email' => 'draft.student@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);
        $program = $this->createProgram('Draft Program', [
            'requirements' => ['Certificate of Registration', 'Grades Transcript'],
        ]);

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/applications/drafts', [
            'studentId' => $student->id,
            'programId' => $program->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('application.status', 'Draft');
        $response->assertJsonPath('application.applicantId', $student->id);
        $this->assertDatabaseCount('application_documents', 2);
    }

    public function test_admin_can_create_user_and_assign_programs(): void
    {
        $adminUser = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin.user@example.com',
            'password' => 'password',
        ]);
        $firstProgram = $this->createProgram('Assigned Program One');
        $secondProgram = $this->createProgram('Assigned Program Two');

        Sanctum::actingAs($adminUser);

        $createResponse = $this->postJson('/api/users', [
            'name' => 'New Officer',
            'email' => 'new.officer@example.com',
            'role' => 'officer',
        ]);

        $createResponse->assertCreated();
        $createResponse->assertJsonPath('user.email', 'new.officer@example.com');
        $createResponse->assertJsonPath('user.role', 'officer');

        $userId = $createResponse->json('user.id');

        $assignResponse = $this->putJson("/api/users/{$userId}/programs", [
            'programIds' => [$firstProgram->id, $secondProgram->id],
        ]);

        $assignResponse->assertOk();
        $assignResponse->assertJsonPath('user.assignedProgramIds.0', $firstProgram->id);
        $assignResponse->assertJsonPath('user.assignedProgramIds.1', $secondProgram->id);
    }

    public function test_admin_can_send_notification_to_one_student(): void
    {
        $adminUser = User::factory()->admin()->create([
            'name' => 'Notification Admin',
            'email' => 'notification.admin@example.com',
            'password' => 'password',
        ]);
        $studentUser = User::factory()->create([
            'name' => 'Notification Student',
            'email' => 'notification.student@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);
        $otherStudentUser = User::factory()->create([
            'name' => 'Other Student',
            'email' => 'other.student@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->postJson('/api/notifications', [
            'userId' => $studentUser->id,
            'type' => 'admin',
            'title' => 'Interview Schedule',
            'message' => 'Please check your scholarship interview schedule.',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('notification.userId', $studentUser->id);
        $response->assertJsonPath('notification.title', 'Interview Schedule');

        $this->assertDatabaseHas('scholarship_notifications', [
            'user_id' => $studentUser->id,
            'role' => null,
            'type' => 'admin',
            'title' => 'Interview Schedule',
        ]);

        Sanctum::actingAs($studentUser);

        $studentResponse = $this->getJson('/api/notifications');

        $studentResponse->assertOk();
        $studentResponse->assertJsonCount(1, 'notifications');
        $studentResponse->assertJsonPath('notifications.0.title', 'Interview Schedule');

        Sanctum::actingAs($otherStudentUser);

        $otherStudentResponse = $this->getJson('/api/notifications');

        $otherStudentResponse->assertOk();
        $otherStudentResponse->assertJsonCount(0, 'notifications');
    }

    public function test_admin_can_accept_application_and_create_scholar_record(): void
    {
        $adminUser = User::factory()->admin()->create([
            'name' => 'Review Admin',
            'email' => 'review.admin@example.com',
            'password' => 'password',
        ]);
        $studentUser = User::factory()->create([
            'name' => 'Review Student',
            'email' => 'review.student@example.com',
            'password' => 'password',
            'role' => 'student',
            'school_name' => 'ScholarSync State University',
        ]);
        $program = $this->createProgram('Review Program', [
            'requirements' => ['Certificate of Registration', 'Grades Transcript'],
        ]);
        $application = ScholarshipApplication::create([
            'scholarship_program_id' => $program->id,
            'applicant_id' => $studentUser->id,
            'application_no' => 'APP-2026-99999',
            'status' => 'Submitted',
            'risk_label' => 'Stable',
            'score' => 80,
            'progress' => 35,
            'remarks' => 'Submitted for review.',
            'next_action' => 'Validate documents.',
            'missing_requirements' => $program->requirements,
            'timeline' => [
                [
                    'status' => 'Draft',
                    'label' => 'Draft Created',
                    'remarks' => 'Draft started.',
                    'date' => 'May 01, 2026',
                ],
                [
                    'status' => 'Submitted',
                    'label' => 'Submitted',
                    'remarks' => 'Submitted for review.',
                    'date' => 'May 02, 2026',
                ],
            ],
            'submitted_at' => now()->subDays(2),
        ]);

        ApplicationDocument::create([
            'scholarship_application_id' => $application->id,
            'name' => 'Certificate of Registration',
            'type' => 'PDF',
            'path' => 'applications/99999/cor.pdf',
            'status' => 'Pending',
            'remarks' => 'Pending validation.',
            'uploaded_by_id' => $studentUser->id,
            'validated_by_id' => null,
            'uploaded_at' => now()->subDay(),
        ]);

        ApplicationDocument::create([
            'scholarship_application_id' => $application->id,
            'name' => 'Grades Transcript',
            'type' => 'PDF',
            'path' => 'applications/99999/transcript.pdf',
            'status' => 'Pending',
            'remarks' => 'Pending validation.',
            'uploaded_by_id' => $studentUser->id,
            'validated_by_id' => null,
            'uploaded_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($adminUser);

        $response = $this->patchJson("/api/applications/{$application->id}/status", [
            'status' => 'Accepted',
            'remarks' => 'Approved for scholarship.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('application.status', 'Accepted');
        $this->assertDatabaseHas('scholars', [
            'scholarship_application_id' => $application->id,
            'name' => $studentUser->name,
        ]);
        $this->assertDatabaseHas('scholarship_programs', [
            'id' => $program->id,
            'used_slots' => 1,
        ]);
        $this->assertDatabaseHas('scholarship_notifications', [
            'user_id' => $studentUser->id,
            'role' => null,
            'type' => 'success',
            'title' => 'Application Status Updated',
        ]);
    }

    /**
     * Create a scholarship program with default test data.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createProgram(string $name, array $overrides = []): ScholarshipProgram
    {
        return ScholarshipProgram::create(array_merge([
            'name' => $name,
            'provider' => 'ScholarSync Foundation',
            'category' => 'Merit',
            'type' => 'Scholarship',
            'description' => $name.' description.',
            'eligibility_summary' => $name.' summary.',
            'status' => 'Open',
            'slots' => 10,
            'used_slots' => 0,
            'budget' => 500000,
            'schedule' => [
                'opening' => 'May 01, 2026',
                'deadline' => 'June 01, 2026',
                'screening' => 'June 10, 2026',
                'awarding' => 'June 20, 2026',
            ],
            'eligibility' => ['Currently enrolled'],
            'requirements' => ['Transcript'],
            'scoring_criteria' => ['Grades'],
            'renewal_rules' => ['Keep GPA'],
            'assigned_admin_ids' => [],
            'published_at' => now(),
        ], $overrides));
    }
}
