<?php

namespace Tests\Feature\Api;

use App\Mail\StudentRegistrationMail;
use App\Models\ApplicationDocument;
use App\Models\Scholar;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipNotification;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

    public function test_student_can_register_with_profile_details_and_login_with_birthdate_password(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/register', [
            'fullName' => 'Register Student',
            'email' => 'register.student@example.com',
            'birthDate' => '2004-05-12',
            'gender' => 'Female',
            'schoolName' => 'ScholarSync State University',
            'studentId' => 'REG-2026-0001',
            'course' => 'BSIT',
            'yearLevel' => '2nd Year',
            'semester' => '1st Semester',
            'academicYear' => '2025-2026',
            'gpa' => 95,
            'enrollmentStatus' => 'Currently Enrolled',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('user.role', 'student');
        $response->assertJsonPath('user.email', 'register.student@example.com');
        Mail::assertQueued(StudentRegistrationMail::class, function (StudentRegistrationMail $mail): bool {
            return $mail->hasTo('register.student@example.com');
        });

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => 'register.student@example.com',
            'password' => '051204',
        ]);

        $loginResponse->assertOk();
        $loginResponse->assertJsonPath('user.email', 'register.student@example.com');
    }

    public function test_authenticated_user_can_logout_without_server_error(): void
    {
        $student = User::factory()->create([
            'name' => 'Logout Student',
            'email' => 'logout.student@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);

        $loginResponse = $this->postJson('/api/auth/login', [
            'email' => $student->email,
            'password' => 'password',
        ]);
        $token = $loginResponse->json('token');

        $response = $this
            ->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertNoContent();
    }

    public function test_authenticated_user_can_mark_all_notifications_read_without_server_error(): void
    {
        $student = User::factory()->create([
            'name' => 'Notification Reader',
            'email' => 'notification.reader@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);
        $notification = ScholarshipNotification::factory()->create([
            'user_id' => $student->id,
            'role' => null,
            'read_at' => null,
        ]);

        Sanctum::actingAs($student);

        $response = $this->patchJson('/api/notifications/read-all');

        $response->assertNoContent();
        $this->assertNotNull($notification->fresh()->read_at);
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
            'requirements' => ['Certificate of Ratings', 'Certificate of Indigency'],
        ]);

        Sanctum::actingAs($student);

        $response = $this->postJson('/api/applications/drafts', [
            'studentId' => $student->id,
            'programId' => $program->id,
            'name' => 'Draft Student Updated',
            'gender' => 'Female',
            'birthDate' => '2004-05-18',
            'civilStatus' => 'Single',
            'citizenship' => 'Filipino',
            'address' => 'Zone 1',
            'barangay' => 'Poblacion',
            'city' => 'Bislig City',
            'province' => 'Surigao del Sur',
            'contactNumber' => '09171234567',
            'schoolName' => 'Draft University',
            'applicantStudentId' => 'STU-2026-001',
            'course' => 'BS Information Technology',
            'yearLevel' => '3rd Year',
            'semester' => 'First Semester',
            'academicYear' => '2026-2027',
            'gpa' => 93.25,
            'familyIncome' => 120000,
            'enrollmentStatus' => 'Enrolled',
            'parentOccupation' => 'Fisher',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('application.status', 'Draft');
        $response->assertJsonPath('application.applicantId', $student->id);
        $response->assertJsonPath('application.applicant.name', 'Draft Student Updated');
        $response->assertJsonPath('application.applicant.studentId', 'STU-2026-001');
        $response->assertJsonPath('application.applicant.schoolName', 'Draft University');
        $response->assertJsonPath('application.applicant.birthDate', '2004-05-18');
        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'contact_number' => '09171234567',
            'course' => 'BS Information Technology',
            'gpa' => 93.25,
        ]);
        $this->assertDatabaseCount('application_documents', 2);
    }

    public function test_student_can_submit_application_with_uploaded_requirement_documents(): void
    {
        Storage::fake('local');

        $student = User::factory()->create([
            'name' => 'Submit Student',
            'email' => 'submit.student@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);
        $program = $this->createProgram('Submit Program', [
            'requirements' => ['Certificate of Ratings'],
        ]);

        Sanctum::actingAs($student);

        $draftResponse = $this->postJson('/api/applications/drafts', [
            'studentId' => $student->id,
            'programId' => $program->id,
        ]);
        $applicationId = $draftResponse->json('application.id');

        $this->postJson("/api/applications/{$applicationId}/documents", [
            'requirementName' => 'Certificate of Ratings',
            'file' => UploadedFile::fake()->create('registration.pdf', 128, 'application/pdf'),
        ])->assertCreated();

        $document = ApplicationDocument::query()->where('scholarship_application_id', $applicationId)->firstOrFail();
        Storage::disk('local')->assertExists($document->path);

        $submitResponse = $this->postJson("/api/applications/{$applicationId}/submit");

        $submitResponse->assertOk();
        $submitResponse->assertJsonPath('application.status', 'Submitted');
        $submitResponse->assertJsonPath('application.progress', 25);
        $submitResponse->assertJsonPath('application.missingRequirements', []);
        $this->assertNotNull(ScholarshipApplication::query()->find($applicationId)?->submitted_at);
    }

    public function test_student_can_submit_application_with_to_follow_documents_missing(): void
    {
        Storage::fake('local');

        $student = User::factory()->create([
            'name' => 'To Follow Student',
            'email' => 'to.follow.student@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);
        $program = $this->createProgram('To Follow Program', [
            'requirements' => ['Certificate of Indigency', 'Certificate of Enrollment / COE'],
            'requirement_rules' => [
                [
                    'name' => 'Certificate of Indigency',
                    'stage' => 'application',
                    'isRequired' => true,
                    'allowToFollow' => false,
                ],
                [
                    'name' => 'Certificate of Enrollment / COE',
                    'stage' => 'application',
                    'isRequired' => true,
                    'allowToFollow' => true,
                ],
            ],
        ]);

        Sanctum::actingAs($student);

        $draftResponse = $this->postJson('/api/applications/drafts', [
            'studentId' => $student->id,
            'programId' => $program->id,
        ]);
        $applicationId = $draftResponse->json('application.id');

        $this->postJson("/api/applications/{$applicationId}/documents", [
            'requirementName' => 'Certificate of Indigency',
            'file' => UploadedFile::fake()->create('indigency.pdf', 128, 'application/pdf'),
        ])->assertCreated();

        $submitResponse = $this->postJson("/api/applications/{$applicationId}/submit");

        $submitResponse->assertOk();
        $submitResponse->assertJsonPath('application.status', 'Submitted');
        $submitResponse->assertJsonPath('application.missingRequirements.0', 'Certificate of Enrollment / COE');
    }

    public function test_student_cannot_submit_application_with_missing_requirement_documents(): void
    {
        $student = User::factory()->create([
            'name' => 'Missing Requirement Student',
            'email' => 'missing.requirement@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);
        $program = $this->createProgram('Missing Requirement Program', [
            'requirements' => ['Certificate of Ratings'],
        ]);

        Sanctum::actingAs($student);

        $draftResponse = $this->postJson('/api/applications/drafts', [
            'studentId' => $student->id,
            'programId' => $program->id,
        ]);
        $applicationId = $draftResponse->json('application.id');

        $submitResponse = $this->postJson("/api/applications/{$applicationId}/submit");

        $submitResponse->assertUnprocessable();
        $submitResponse->assertJsonPath('errors.documents.0', 'Certificate of Ratings');
        $this->assertDatabaseHas('scholarship_applications', [
            'id' => $applicationId,
            'status' => 'Draft',
        ]);
    }

    public function test_head_officer_can_create_user_and_assign_programs(): void
    {
        $headOfficer = User::factory()->headOfficer()->create([
            'name' => 'Head Officer User',
            'email' => 'head.officer.user@example.com',
            'password' => 'password',
        ]);
        $firstProgram = $this->createProgram('Assigned Program One');
        $secondProgram = $this->createProgram('Assigned Program Two');

        Sanctum::actingAs($headOfficer);

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

    public function test_head_officer_can_send_notification_to_one_student(): void
    {
        $headOfficer = User::factory()->headOfficer()->create([
            'name' => 'Notification Head Officer',
            'email' => 'notification.head.officer@example.com',
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

        Sanctum::actingAs($headOfficer);

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

    public function test_head_officer_can_accept_application_and_create_scholar_record(): void
    {
        $headOfficer = User::factory()->headOfficer()->create([
            'name' => 'Review Head Officer',
            'email' => 'review.head.officer@example.com',
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
            'requirements' => ['Certificate of Ratings', 'Certificate of Indigency'],
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
            'name' => 'Certificate of Ratings',
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
            'name' => 'Certificate of Indigency',
            'type' => 'PDF',
            'path' => 'applications/99999/indigency.pdf',
            'status' => 'Pending',
            'remarks' => 'Pending validation.',
            'uploaded_by_id' => $studentUser->id,
            'validated_by_id' => null,
            'uploaded_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($headOfficer);

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

    public function test_officer_application_review_queue_returns_assigned_review_applications_with_applicant_data(): void
    {
        $assignedProgram = $this->createProgram('Assigned Review Program');
        $unassignedProgram = $this->createProgram('Unassigned Review Program');
        $officer = User::factory()->officer()->create([
            'name' => 'Assigned Review Officer',
            'email' => 'assigned.review.officer@example.com',
            'password' => 'password',
            'assigned_program_ids' => [$assignedProgram->id],
        ]);
        $student = User::factory()->create([
            'name' => 'Review Queue Student',
            'email' => 'review.queue.student@example.com',
            'gpa' => 94.25,
        ]);
        $reviewApplication = ScholarshipApplication::factory()
            ->for($assignedProgram, 'program')
            ->for($student, 'applicant')
            ->underReview()
            ->create([
                'application_no' => 'APP-REVIEW-001',
            ]);

        $submittedApplication = ScholarshipApplication::factory()
            ->for($assignedProgram, 'program')
            ->for($student, 'applicant')
            ->submitted()
            ->create([
                'application_no' => 'APP-SUBMITTED-001',
            ]);

        ScholarshipApplication::factory()
            ->for($unassignedProgram, 'program')
            ->for($student, 'applicant')
            ->underReview()
            ->create([
                'application_no' => 'APP-HIDDEN-001',
            ]);

        Sanctum::actingAs($officer);

        $response = $this->getJson('/api/applications/review?search=Review%20Queue&status=Under%20Review');

        $response->assertOk();
        $response->assertJsonCount(1, 'applications');
        $response->assertJsonPath('applications.0.id', $reviewApplication->id);
        $response->assertJsonPath('applications.0.applicant.name', $student->name);
        $response->assertJsonPath('applications.0.applicantGpa', 94.25);
        $response->assertJsonPath('applications.0.programName', $assignedProgram->name);

        $this->getJson('/api/applications/review?status=Submitted')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.id', $submittedApplication->id);
    }

    public function test_officer_only_sees_assigned_program_applications_programs_and_scholars(): void
    {
        $tdpProgram = $this->createProgram('TDP Program');
        $chedProgram = $this->createProgram('CHED Program');
        $officer = User::factory()->create([
            'name' => 'TDP Officer',
            'email' => 'tdp.officer@example.com',
            'password' => 'password',
            'role' => 'officer',
            'assigned_program_ids' => [$tdpProgram->id],
        ]);
        $student = User::factory()->create([
            'name' => 'Scoped Student',
            'email' => 'scoped.student@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);

        $tdpApplication = ScholarshipApplication::create([
            'scholarship_program_id' => $tdpProgram->id,
            'applicant_id' => $student->id,
            'application_no' => 'APP-TDP-001',
            'status' => 'Submitted',
            'risk_label' => 'Stable',
            'score' => 80,
            'progress' => 35,
            'remarks' => 'Submitted.',
            'next_action' => 'Validate documents.',
            'missing_requirements' => [],
            'timeline' => [],
        ]);
        $chedApplication = ScholarshipApplication::create([
            'scholarship_program_id' => $chedProgram->id,
            'applicant_id' => $student->id,
            'application_no' => 'APP-CHED-001',
            'status' => 'Submitted',
            'risk_label' => 'Stable',
            'score' => 80,
            'progress' => 35,
            'remarks' => 'Submitted.',
            'next_action' => 'Validate documents.',
            'missing_requirements' => [],
            'timeline' => [],
        ]);
        Scholar::create([
            'user_id' => $student->id,
            'scholarship_program_id' => $tdpProgram->id,
            'scholarship_application_id' => $tdpApplication->id,
            'scholar_id' => 'SCH-TDP-001',
            'name' => 'TDP Scholar',
            'email' => 'tdp.scholar@example.com',
            'program' => $tdpProgram->name,
            'scholarship_status' => 'Active Scholar',
            'renewal_status' => 'Active Scholar',
            'compliance_status' => 'Compliant',
            'compliance_rate' => 100,
            'risk_label' => 'Low Risk',
            'submissions' => [],
        ]);
        Scholar::create([
            'user_id' => $student->id,
            'scholarship_program_id' => $chedProgram->id,
            'scholarship_application_id' => $chedApplication->id,
            'scholar_id' => 'SCH-CHED-001',
            'name' => 'CHED Scholar',
            'email' => 'ched.scholar@example.com',
            'program' => $chedProgram->name,
            'scholarship_status' => 'Active Scholar',
            'renewal_status' => 'Active Scholar',
            'compliance_status' => 'Compliant',
            'compliance_rate' => 100,
            'risk_label' => 'Low Risk',
            'submissions' => [],
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/programs')
            ->assertOk()
            ->assertJsonCount(1, 'programs')
            ->assertJsonPath('programs.0.name', 'TDP Program');

        $this->getJson('/api/applications')
            ->assertOk()
            ->assertJsonCount(1, 'applications')
            ->assertJsonPath('applications.0.applicationNo', 'APP-TDP-001');

        $this->getJson('/api/scholars')
            ->assertOk()
            ->assertJsonCount(1, 'scholars')
            ->assertJsonPath('scholars.0.program', 'TDP Program');
    }

    public function test_officer_cannot_manage_users_or_create_programs(): void
    {
        $program = $this->createProgram('Officer Assigned Program');
        $officer = User::factory()->create([
            'name' => 'Limited Officer',
            'email' => 'limited.officer@example.com',
            'password' => 'password',
            'role' => 'officer',
            'assigned_program_ids' => [$program->id],
        ]);

        Sanctum::actingAs($officer);

        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/users', [
            'name' => 'Blocked Officer',
            'email' => 'blocked.officer@example.com',
            'role' => 'officer',
            'programIds' => [$program->id],
        ])->assertForbidden();
        $this->postJson('/api/programs', [
            'name' => 'Blocked Program',
            'provider' => 'BLOCK',
            'category' => 'Blocked',
            'type' => 'Scholarship',
            'description' => 'Should not be created by an officer.',
            'status' => 'Open',
        ])->assertForbidden();
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
