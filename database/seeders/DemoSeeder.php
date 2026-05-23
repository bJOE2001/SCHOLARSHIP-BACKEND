<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\ApplicationDocument;
use App\Models\GrantAnnouncement;
use App\Models\GrantBatch;
use App\Models\GrantBeneficiary;
use App\Models\Scholar;
use App\Models\ScholarComplianceSubmission;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipNotification;
use App\Models\ScholarshipProgram;
use App\Models\SemesterRequirementDraft;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Model::unguarded(function (): void {
            DB::transaction(function (): void {
                $users = $this->seedUsers();
                $programs = $this->seedPrograms($users);
                $applications = $this->seedApplications($users, $programs);

                $this->seedDocuments($users, $applications);

                $scholars = $this->seedScholars($users, $programs, $applications);

                $this->seedComplianceSubmissions($scholars);
                $this->seedAnnouncements($users, $programs);
                $grantData = $this->seedGrantDistribution($users, $programs, $scholars);

                $this->seedNotifications($users, $applications, $scholars, $grantData);
                $this->seedSettings($users);
                $this->seedSemesterRequirementDrafts($scholars);
            });
        });
    }

    /**
     * @return array<string, User>
     */
    private function seedUsers(): array
    {
        $userRows = [
            'headOfficer' => [
                'name' => 'Head Scholarship Officer',
                'email' => 'head.officer@example.com',
                'role' => 'head_officer',
                'department' => 'Scholarship Programs Office',
            ],
            'tdpOfficer' => [
                'name' => 'TDP Scholarship Officer',
                'email' => 'tdp.officer@example.com',
                'role' => 'officer',
                'department' => 'Scholarship Programs Office',
            ],
            'meritOfficer' => [
                'name' => 'Merit Scholarship Officer',
                'email' => 'merit.officer@example.com',
                'role' => 'officer',
                'department' => 'Scholarship Programs Office',
            ],
            'studentOne' => [
                'name' => 'Ana Reyes',
                'email' => 'student1@example.com',
                'student_id' => 'STU-2026-0001',
                'birth_date' => '2004-04-18',
                'gender' => 'Female',
                'barangay' => 'Poblacion',
                'address' => 'Purok 2, Mabini Street',
                'course' => 'BS Information Technology',
                'year_level' => '3rd Year',
                'gpa' => 94.75,
                'family_income' => 120000,
            ],
            'studentTwo' => [
                'name' => 'Marco Santos',
                'email' => 'student2@example.com',
                'student_id' => 'STU-2026-0002',
                'birth_date' => '2003-11-09',
                'gender' => 'Male',
                'barangay' => 'Mangagoy',
                'address' => 'Rizal Avenue',
                'course' => 'BS Accountancy',
                'year_level' => '4th Year',
                'gpa' => 92.30,
                'family_income' => 160000,
            ],
            'studentThree' => [
                'name' => 'Liza Cruz',
                'email' => 'student3@example.com',
                'student_id' => 'STU-2026-0003',
                'birth_date' => '2005-02-21',
                'gender' => 'Female',
                'barangay' => 'Tabon',
                'address' => 'San Isidro Road',
                'course' => 'BS Education',
                'year_level' => '2nd Year',
                'gpa' => 78.45,
                'family_income' => 90000,
            ],
            'studentFour' => [
                'name' => 'Paolo Dela Cruz',
                'email' => 'student4@example.com',
                'student_id' => 'STU-2026-0004',
                'birth_date' => '2004-08-30',
                'gender' => 'Male',
                'barangay' => 'San Vicente',
                'address' => 'National Highway',
                'course' => 'BS Civil Engineering',
                'year_level' => '3rd Year',
                'gpa' => 88.20,
                'family_income' => 180000,
            ],
            'studentFive' => [
                'name' => 'Mika Villanueva',
                'email' => 'student5@example.com',
                'student_id' => 'STU-2026-0005',
                'birth_date' => '2006-01-12',
                'gender' => 'Female',
                'barangay' => 'Coleto',
                'address' => 'Sampaguita Street',
                'course' => 'BS Social Work',
                'year_level' => '1st Year',
                'gpa' => 90.10,
                'family_income' => 110000,
            ],
        ];

        return collect($userRows)
            ->mapWithKeys(function (array $attributes, string $key): array {
                $isStudent = ($attributes['role'] ?? 'student') === 'student';
                $user = User::query()->updateOrCreate(
                    ['email' => $attributes['email']],
                    array_merge($this->defaultUserAttributes($isStudent), $attributes),
                );

                return [$key => $user->refresh()];
            })
            ->all();
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, ScholarshipProgram>
     */
    private function seedPrograms(array $users): array
    {
        $programRows = [
            'tdp' => [
                'name' => 'Tulong Dunong Program',
                'provider' => 'TDP',
                'category' => 'Government Assistance',
                'type' => 'Grant',
                'description' => 'Supports qualified enrolled students through semester-based educational assistance.',
                'eligibility_summary' => 'Open to enrolled students with complete documentary requirements and verified financial need.',
                'status' => 'Open',
                'slots' => 100,
                'used_slots' => 2,
                'budget' => 3000000,
                'officerKeys' => ['headOfficer', 'tdpOfficer'],
            ],
            'merit' => [
                'name' => 'Merit Scholars Grant',
                'provider' => 'MERIT',
                'category' => 'Academic Merit',
                'type' => 'Scholarship',
                'description' => 'Rewards high-performing students who maintain strong grades and leadership involvement.',
                'eligibility_summary' => 'Students must maintain at least 90 percent general weighted average and submit renewal requirements.',
                'status' => 'Open',
                'slots' => 45,
                'used_slots' => 1,
                'budget' => 1800000,
                'officerKeys' => ['headOfficer', 'meritOfficer'],
            ],
            'stem' => [
                'name' => 'STEM Excellence Scholarship',
                'provider' => 'STEM',
                'category' => 'Science and Technology',
                'type' => 'Scholarship',
                'description' => 'Funds students in priority science, engineering, and technology programs.',
                'eligibility_summary' => 'Applicants must be enrolled in a STEM course and pass academic screening.',
                'status' => 'Closing Soon',
                'slots' => 30,
                'used_slots' => 0,
                'budget' => 2200000,
                'officerKeys' => ['headOfficer', 'meritOfficer'],
            ],
            'community' => [
                'name' => 'Community Service Grant',
                'provider' => 'CSG',
                'category' => 'Community',
                'type' => 'Grant',
                'description' => 'Assists students with documented community service and local leadership participation.',
                'eligibility_summary' => 'Applicants must submit service records, enrollment proof, and barangay certification.',
                'status' => 'Closed',
                'slots' => 25,
                'used_slots' => 0,
                'budget' => 750000,
                'officerKeys' => ['headOfficer'],
            ],
        ];

        return collect($programRows)
            ->mapWithKeys(function (array $attributes, string $key) use ($users): array {
                $officerKeys = $attributes['officerKeys'];
                unset($attributes['officerKeys']);

                $program = ScholarshipProgram::query()->updateOrCreate(
                    ['provider' => $attributes['provider']],
                    array_merge($attributes, $this->programDefaults()),
                );
                $officerIds = collect($officerKeys)
                    ->map(fn (string $officerKey): int => $users[$officerKey]->id)
                    ->all();

                $program->assignedOfficers()->syncWithoutDetaching($officerIds);

                return [$key => $program->refresh()];
            })
            ->all();
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, ScholarshipProgram>  $programs
     * @return array<string, ScholarshipApplication>
     */
    private function seedApplications(array $users, array $programs): array
    {
        $applicationRows = [
            'anaTdp' => [
                'application_no' => 'APP-DEMO-TDP-0001',
                'programKey' => 'tdp',
                'userKey' => 'studentOne',
                'reviewerKey' => 'tdpOfficer',
                'status' => 'Active Scholar',
                'risk_label' => 'Stable',
                'score' => 96,
                'progress' => 100,
                'remarks' => 'Approved and monitored as an active scholar.',
                'next_action' => 'Wait for the grant claiming schedule.',
                'missing_requirements' => [],
                'submittedDaysAgo' => 28,
                'reviewedDaysAgo' => 20,
            ],
            'marcoMerit' => [
                'application_no' => 'APP-DEMO-MERIT-0002',
                'programKey' => 'merit',
                'userKey' => 'studentTwo',
                'reviewerKey' => 'meritOfficer',
                'status' => 'Active Scholar',
                'risk_label' => 'Stable',
                'score' => 94,
                'progress' => 100,
                'remarks' => 'Awarded after final ranking validation.',
                'next_action' => 'Maintain renewal compliance each semester.',
                'missing_requirements' => [],
                'submittedDaysAgo' => 24,
                'reviewedDaysAgo' => 16,
            ],
            'lizaTdp' => [
                'application_no' => 'APP-DEMO-TDP-0003',
                'programKey' => 'tdp',
                'userKey' => 'studentThree',
                'reviewerKey' => 'tdpOfficer',
                'status' => 'Pending Renewal',
                'risk_label' => 'At Risk',
                'score' => 78,
                'progress' => 85,
                'remarks' => 'Renewal needs an updated certificate of grades.',
                'next_action' => 'Submit missing semester renewal document.',
                'missing_requirements' => ['Certificate of Ratings'],
                'submittedDaysAgo' => 18,
                'reviewedDaysAgo' => 3,
            ],
            'paoloStem' => [
                'application_no' => 'APP-DEMO-STEM-0004',
                'programKey' => 'stem',
                'userKey' => 'studentFour',
                'reviewerKey' => 'meritOfficer',
                'status' => 'Needs Revision',
                'risk_label' => 'Borderline',
                'score' => 81,
                'progress' => 45,
                'remarks' => 'Certificate of Enrollment is blurry and must be replaced.',
                'next_action' => 'Upload a clear copy of the Certificate of Enrollment.',
                'missing_requirements' => ['Certificate of Enrollment / COE'],
                'submittedDaysAgo' => 7,
                'reviewedDaysAgo' => 1,
            ],
            'mikaCommunity' => [
                'application_no' => 'APP-DEMO-CSG-0005',
                'programKey' => 'community',
                'userKey' => 'studentFive',
                'reviewerKey' => 'headOfficer',
                'status' => 'Submitted',
                'risk_label' => 'Stable',
                'score' => 0,
                'progress' => 35,
                'remarks' => 'Application received and queued for screening.',
                'next_action' => 'Wait for officer validation.',
                'missing_requirements' => [],
                'submittedDaysAgo' => 2,
                'reviewedDaysAgo' => null,
            ],
        ];

        return collect($applicationRows)
            ->mapWithKeys(function (array $attributes, string $key) use ($users, $programs): array {
                $program = $programs[$attributes['programKey']];
                $applicant = $users[$attributes['userKey']];
                $reviewer = $attributes['reviewedDaysAgo'] === null ? null : $users[$attributes['reviewerKey']];

                $application = ScholarshipApplication::query()->updateOrCreate(
                    ['application_no' => $attributes['application_no']],
                    [
                        'scholarship_program_id' => $program->id,
                        'applicant_id' => $applicant->id,
                        'status' => $attributes['status'],
                        'risk_label' => $attributes['risk_label'],
                        'score' => $attributes['score'],
                        'progress' => $attributes['progress'],
                        'remarks' => $attributes['remarks'],
                        'next_action' => $attributes['next_action'],
                        'missing_requirements' => $attributes['missing_requirements'],
                        'timeline' => $this->applicationTimeline($attributes['status'], $attributes['remarks']),
                        'submitted_at' => now()->subDays($attributes['submittedDaysAgo']),
                        'reviewed_at' => $attributes['reviewedDaysAgo'] === null ? null : now()->subDays($attributes['reviewedDaysAgo']),
                        'reviewed_by_id' => $reviewer?->id,
                    ],
                );

                return [$key => $application->refresh()];
            })
            ->all();
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, ScholarshipApplication>  $applications
     */
    private function seedDocuments(array $users, array $applications): void
    {
        $documentRows = [
            'anaTdp' => [
                ['name' => 'Certificate of Enrollment / COE', 'status' => 'Accepted', 'remarks' => 'Verified with registrar records.'],
                ['name' => 'Certificate of Ratings', 'status' => 'Accepted', 'remarks' => 'Grades meet the program requirement.'],
                ['name' => 'Certificate of Indigency', 'status' => 'Accepted', 'remarks' => 'Barangay certification accepted.'],
            ],
            'marcoMerit' => [
                ['name' => 'Certificate of Enrollment / COE', 'status' => 'Accepted', 'remarks' => 'Enrollment is verified.'],
                ['name' => 'Certificate of Ratings', 'status' => 'Accepted', 'remarks' => 'Academic rating validated.'],
                ['name' => 'Certificate of Indigency', 'status' => 'Accepted', 'remarks' => 'Requirement accepted.'],
            ],
            'lizaTdp' => [
                ['name' => 'Certificate of Enrollment / COE', 'status' => 'Accepted', 'remarks' => 'Enrollment is verified.'],
                ['name' => 'Certificate of Ratings', 'status' => 'Missing', 'remarks' => 'Updated rating certificate is required.'],
                ['name' => 'Certificate of Indigency', 'status' => 'Accepted', 'remarks' => 'Requirement accepted.'],
            ],
            'paoloStem' => [
                ['name' => 'Certificate of Enrollment / COE', 'status' => 'Rejected', 'remarks' => 'Uploaded copy is blurry.'],
                ['name' => 'Certificate of Ratings', 'status' => 'Pending', 'remarks' => 'Waiting for officer validation.'],
                ['name' => 'Certificate of Indigency', 'status' => 'Accepted', 'remarks' => 'Requirement accepted.'],
            ],
            'mikaCommunity' => [
                ['name' => 'Certificate of Enrollment / COE', 'status' => 'Pending', 'remarks' => 'Pending validation.'],
                ['name' => 'Certificate of Ratings', 'status' => 'Pending', 'remarks' => 'Pending validation.'],
                ['name' => 'Certificate of Indigency', 'status' => 'Pending', 'remarks' => 'Pending validation.'],
            ],
        ];

        foreach ($documentRows as $applicationKey => $documents) {
            $application = $applications[$applicationKey];
            $uploadedBy = $application->applicant;
            $validatedBy = $application->reviewer ?? $users['headOfficer'];

            foreach ($documents as $document) {
                $isMissing = $document['status'] === 'Missing';
                $documentSlug = str($document['name'])->slug()->toString();

                ApplicationDocument::query()->updateOrCreate(
                    [
                        'scholarship_application_id' => $application->id,
                        'name' => $document['name'],
                    ],
                    [
                        'type' => 'pdf',
                        'path' => $isMissing ? null : "demo-documents/{$application->application_no}/{$documentSlug}.pdf",
                        'status' => $document['status'],
                        'remarks' => $document['remarks'],
                        'uploaded_by_id' => $isMissing ? null : $uploadedBy?->id,
                        'validated_by_id' => in_array($document['status'], ['Accepted', 'Rejected'], true) ? $validatedBy?->id : null,
                        'uploaded_at' => $isMissing ? null : now()->subDays(6),
                    ],
                );
            }
        }
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, ScholarshipProgram>  $programs
     * @param  array<string, ScholarshipApplication>  $applications
     * @return array<string, Scholar>
     */
    private function seedScholars(array $users, array $programs, array $applications): array
    {
        $scholarRows = [
            'anaTdp' => [
                'scholar_id' => 'SCH-DEMO-0001',
                'userKey' => 'studentOne',
                'programKey' => 'tdp',
                'applicationKey' => 'anaTdp',
                'scholarship_status' => 'Active Scholar',
                'renewal_status' => 'Approved',
                'compliance_status' => 'Compliant',
                'compliance_rate' => 100,
                'risk_label' => 'Stable',
                'risk_reason' => 'All semester requirements are complete.',
                'recommended_action' => 'Continue regular monitoring.',
                'submissions' => $this->semesterSubmissions('Accepted', 'Accepted', 'Accepted'),
            ],
            'marcoMerit' => [
                'scholar_id' => 'SCH-DEMO-0002',
                'userKey' => 'studentTwo',
                'programKey' => 'merit',
                'applicationKey' => 'marcoMerit',
                'scholarship_status' => 'Active Scholar',
                'renewal_status' => 'Approved',
                'compliance_status' => 'Pending Review',
                'compliance_rate' => 85,
                'risk_label' => 'Borderline',
                'risk_reason' => 'Submitted grades need final officer review.',
                'recommended_action' => 'Review encoded grades and mark renewal status.',
                'submissions' => $this->semesterSubmissions('Accepted', 'Accepted', 'Pending'),
            ],
            'lizaTdp' => [
                'scholar_id' => 'SCH-DEMO-0003',
                'userKey' => 'studentThree',
                'programKey' => 'tdp',
                'applicationKey' => 'lizaTdp',
                'scholarship_status' => 'Active Scholar',
                'renewal_status' => 'Pending Renewal',
                'compliance_status' => 'Missing Requirements',
                'compliance_rate' => 58,
                'risk_label' => 'At Risk',
                'risk_reason' => 'Certificate of ratings has not been submitted for the current semester.',
                'recommended_action' => 'Follow up and hold grant release until documents are complete.',
                'submissions' => $this->semesterSubmissions('Accepted', 'Accepted', 'Missing'),
            ],
        ];

        return collect($scholarRows)
            ->mapWithKeys(function (array $attributes, string $key) use ($users, $programs, $applications): array {
                $student = $users[$attributes['userKey']];
                $program = $programs[$attributes['programKey']];
                $application = $applications[$attributes['applicationKey']];

                $scholar = Scholar::query()->updateOrCreate(
                    ['scholar_id' => $attributes['scholar_id']],
                    [
                        'user_id' => $student->id,
                        'scholarship_program_id' => $program->id,
                        'scholarship_application_id' => $application->id,
                        'name' => $student->name,
                        'avatar' => null,
                        'program' => $program->name,
                        'course' => $student->course,
                        'year_level' => $student->year_level,
                        'school' => $student->school_name,
                        'gender' => $student->gender,
                        'birthdate' => $student->birth_date,
                        'address' => $this->studentAddress($student),
                        'contact_number' => $student->contact_number,
                        'email' => $student->email,
                        'gpa' => $student->gpa,
                        'enrollment_status' => 'Enrolled and Verified',
                        'academic_year' => '2026-2027',
                        'semester' => '1st Semester',
                        'scholarship_status' => $attributes['scholarship_status'],
                        'renewal_status' => $attributes['renewal_status'],
                        'date_approved' => now()->subDays(20)->toDateString(),
                        'duration' => '1 Academic Year',
                        'compliance_status' => $attributes['compliance_status'],
                        'compliance_rate' => $attributes['compliance_rate'],
                        'risk_label' => $attributes['risk_label'],
                        'risk_reason' => $attributes['risk_reason'],
                        'recommended_action' => $attributes['recommended_action'],
                        'submissions' => $attributes['submissions'],
                    ],
                );

                return [$key => $scholar->refresh()];
            })
            ->all();
    }

    /**
     * @param  array<string, Scholar>  $scholars
     */
    private function seedComplianceSubmissions(array $scholars): void
    {
        $submissionRows = [
            'anaTdp' => [
                'status' => 'Compliant',
                'coe_status' => 'Accepted',
                'cor_status' => 'Accepted',
                'grades_status' => 'Accepted',
                'gpa' => 94.75,
                'officer_notes' => 'All requirements verified for renewal.',
                'submitted_at' => now()->subDays(14),
                'reviewed_at' => now()->subDays(10),
            ],
            'marcoMerit' => [
                'status' => 'Under Review',
                'coe_status' => 'Accepted',
                'cor_status' => 'Accepted',
                'grades_status' => 'Pending',
                'gpa' => 92.30,
                'officer_notes' => 'Encoded grades are awaiting final review.',
                'submitted_at' => now()->subDays(6),
                'reviewed_at' => null,
            ],
            'lizaTdp' => [
                'status' => 'Incomplete',
                'coe_status' => 'Accepted',
                'cor_status' => 'Accepted',
                'grades_status' => 'Missing',
                'gpa' => 78.45,
                'officer_notes' => 'Missing certificate of ratings for the current semester.',
                'submitted_at' => now()->subDays(4),
                'reviewed_at' => now()->subDays(2),
            ],
        ];

        foreach ($submissionRows as $scholarKey => $attributes) {
            $scholar = $scholars[$scholarKey];

            ScholarComplianceSubmission::query()->updateOrCreate(
                [
                    'scholar_id' => $scholar->id,
                    'semester' => '1st Semester',
                    'academic_year' => '2026-2027',
                ],
                [
                    'scholarship_application_id' => $scholar->scholarship_application_id,
                    'status' => $attributes['status'],
                    'coe_status' => $attributes['coe_status'],
                    'cor_status' => $attributes['cor_status'],
                    'grades_status' => $attributes['grades_status'],
                    'gpa' => $attributes['gpa'],
                    'submissions' => $this->semesterSubmissions(
                        $attributes['coe_status'],
                        $attributes['cor_status'],
                        $attributes['grades_status'],
                    ),
                    'grades' => $this->gradeRows(),
                    'officer_notes' => $attributes['officer_notes'],
                    'submitted_at' => $attributes['submitted_at'],
                    'reviewed_at' => $attributes['reviewed_at'],
                ],
            );
        }
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, ScholarshipProgram>  $programs
     */
    private function seedAnnouncements(array $users, array $programs): void
    {
        Announcement::query()->updateOrCreate(
            ['title' => 'Demo: Scholarship renewal requirements are now open'],
            [
                'scholarship_program_id' => null,
                'created_by_id' => $users['headOfficer']->id,
                'message' => 'All active scholars may now submit semester renewal requirements through the student portal.',
                'pin' => true,
                'status' => 'Published',
                'published_at' => now()->subDays(1),
            ],
        );

        Announcement::query()->updateOrCreate(
            ['title' => 'Demo: TDP application screening schedule'],
            [
                'scholarship_program_id' => $programs['tdp']->id,
                'created_by_id' => $users['tdpOfficer']->id,
                'message' => 'TDP applicants with complete requirements will be screened this week by the assigned officer.',
                'pin' => false,
                'status' => 'Published',
                'published_at' => now()->subHours(8),
            ],
        );
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, ScholarshipProgram>  $programs
     * @param  array<string, Scholar>  $scholars
     * @return array<string, mixed>
     */
    private function seedGrantDistribution(array $users, array $programs, array $scholars): array
    {
        $tdpBatch = GrantBatch::query()->updateOrCreate(
            [
                'title' => 'Demo TDP 1st Semester Grant Release',
                'school_year' => '2026-2027',
            ],
            [
                'scholarship_program_id' => $programs['tdp']->id,
                'created_by_id' => $users['tdpOfficer']->id,
                'semester' => '1st Semester',
                'amount' => 7500,
                'claiming_start_date' => now()->addDays(3)->toDateString(),
                'claiming_end_date' => now()->addDays(5)->toDateString(),
                'venue' => 'Scholarship Office Window 2',
                'daily_limit' => 40,
                'remarks' => 'Bring student ID and one photocopy of enrollment proof.',
                'status' => 'Open',
            ],
        );
        $meritBatch = GrantBatch::query()->updateOrCreate(
            [
                'title' => 'Demo Merit Scholars Grant Release',
                'school_year' => '2026-2027',
            ],
            [
                'scholarship_program_id' => $programs['merit']->id,
                'created_by_id' => $users['meritOfficer']->id,
                'semester' => '1st Semester',
                'amount' => 10000,
                'claiming_start_date' => now()->subDays(2)->toDateString(),
                'claiming_end_date' => now()->addDay()->toDateString(),
                'venue' => 'Cashier Releasing Area',
                'daily_limit' => 25,
                'remarks' => 'Claiming is by scheduled time slot only.',
                'status' => 'Open',
            ],
        );

        $beneficiaries = [
            'anaTdp' => $this->seedGrantBeneficiary(
                $tdpBatch,
                $scholars['anaTdp'],
                'For Claiming',
                'TDP-DEMO-2026-0001',
            ),
            'lizaTdp' => $this->seedGrantBeneficiary(
                $tdpBatch,
                $scholars['lizaTdp'],
                'On Hold',
                'TDP-DEMO-2026-0002',
            ),
            'marcoMerit' => $this->seedGrantBeneficiary(
                $meritBatch,
                $scholars['marcoMerit'],
                'Claimed',
                'MERIT-DEMO-2026-0001',
                $users['meritOfficer'],
            ),
        ];

        $this->seedGrantAnnouncement($tdpBatch, $users['tdpOfficer']);
        $this->seedGrantAnnouncement($meritBatch, $users['meritOfficer']);

        return [
            'batches' => [
                'tdp' => $tdpBatch->refresh(),
                'merit' => $meritBatch->refresh(),
            ],
            'beneficiaries' => $beneficiaries,
        ];
    }

    private function seedGrantBeneficiary(
        GrantBatch $grantBatch,
        Scholar $scholar,
        string $claimStatus,
        string $referenceNumber,
        ?User $releasedBy = null,
    ): GrantBeneficiary {
        $isClaimed = $claimStatus === 'Claimed';

        return GrantBeneficiary::query()->updateOrCreate(
            [
                'grant_batch_id' => $grantBatch->id,
                'scholar_id' => $scholar->id,
            ],
            [
                'user_id' => $scholar->user_id,
                'released_by_id' => $isClaimed ? $releasedBy?->id : null,
                'scholar_identifier' => $scholar->scholar_id,
                'scholar_name' => $scholar->name,
                'barangay' => $scholar->user?->barangay,
                'course' => $scholar->course,
                'amount' => $grantBatch->amount,
                'assigned_claim_date' => $claimStatus === 'On Hold' ? null : $grantBatch->claiming_start_date,
                'time_slot' => $claimStatus === 'On Hold' ? null : '8:00 AM - 12:00 PM',
                'claim_status' => $claimStatus,
                'notified_at' => now()->subHours(6),
                'claimed_at' => $isClaimed ? now()->subDay() : null,
                'released_by_name' => $isClaimed ? $releasedBy?->name : null,
                'reference_number' => $referenceNumber,
                'claim_method' => $isClaimed ? 'Cash' : null,
                'release_remarks' => $isClaimed ? 'Released during demo grant distribution.' : null,
            ],
        )->refresh();
    }

    private function seedGrantAnnouncement(GrantBatch $grantBatch, User $createdBy): void
    {
        GrantAnnouncement::query()->updateOrCreate(
            ['grant_batch_id' => $grantBatch->id],
            [
                'created_by_id' => $createdBy->id,
                'title' => "{$grantBatch->title} beneficiaries list",
                'message' => "{$grantBatch->program?->name} beneficiaries may check their assigned claiming date and venue.",
                'program_name' => $grantBatch->program?->name ?? 'Scholarship Program',
                'semester' => $grantBatch->semester,
                'school_year' => $grantBatch->school_year,
                'venue' => $grantBatch->venue,
                'total_beneficiaries' => $grantBatch->beneficiaries()->count(),
                'created_by_name' => $createdBy->name,
            ],
        );
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, ScholarshipApplication>  $applications
     * @param  array<string, Scholar>  $scholars
     * @param  array<string, mixed>  $grantData
     */
    private function seedNotifications(array $users, array $applications, array $scholars, array $grantData): void
    {
        $tdpBatch = $grantData['batches']['tdp'];
        $anaBeneficiary = $grantData['beneficiaries']['anaTdp'];

        $notificationRows = [
            [
                'user_id' => $users['studentOne']->id,
                'role' => 'student',
                'type' => 'grant',
                'title' => 'Demo: Grant schedule ready',
                'message' => 'Your TDP grant is ready for claiming on the assigned schedule.',
                'payload' => [
                    'batchId' => $tdpBatch->id,
                    'beneficiaryId' => $anaBeneficiary->id,
                ],
            ],
            [
                'user_id' => $users['studentThree']->id,
                'role' => 'student',
                'type' => 'task',
                'title' => 'Demo: Renewal requirement needed',
                'message' => 'Upload your certificate of ratings to complete your renewal review.',
                'payload' => [
                    'scholarId' => $scholars['lizaTdp']->id,
                ],
            ],
            [
                'user_id' => $users['tdpOfficer']->id,
                'role' => 'officer',
                'type' => 'task',
                'title' => 'Demo: Application needs revision follow-up',
                'message' => 'A STEM application has a rejected enrollment document.',
                'payload' => [
                    'applicationId' => $applications['paoloStem']->id,
                ],
            ],
            [
                'user_id' => null,
                'role' => 'student',
                'type' => 'admin',
                'title' => 'Demo: Scholarship renewal requirements are now open',
                'message' => 'All active scholars may submit current semester renewal requirements.',
                'payload' => [
                    'pinned' => true,
                ],
            ],
        ];

        foreach ($notificationRows as $notificationRow) {
            ScholarshipNotification::query()->updateOrCreate(
                [
                    'user_id' => $notificationRow['user_id'],
                    'title' => $notificationRow['title'],
                ],
                [
                    'role' => $notificationRow['role'],
                    'type' => $notificationRow['type'],
                    'message' => $notificationRow['message'],
                    'notified_at' => now()->subHours(2),
                    'read_at' => null,
                    'payload' => $notificationRow['payload'],
                ],
            );
        }
    }

    /**
     * @param  array<string, User>  $users
     */
    private function seedSettings(array $users): void
    {
        foreach (['headOfficer', 'tdpOfficer', 'meritOfficer'] as $userKey) {
            UserSetting::query()->updateOrCreate(
                ['user_id' => $users[$userKey]->id],
                [
                    'settings' => [
                        'emailAlerts' => true,
                        'riskAlerts' => true,
                        'defaultRange' => 'Last 6 months',
                        'tableDensity' => $userKey === 'headOfficer' ? 'Compact' : 'Comfortable',
                    ],
                ],
            );
        }
    }

    /**
     * @param  array<string, Scholar>  $scholars
     */
    private function seedSemesterRequirementDrafts(array $scholars): void
    {
        $scholar = $scholars['lizaTdp'];

        SemesterRequirementDraft::query()->updateOrCreate(
            [
                'user_id' => $scholar->user_id,
                'scholar_id' => $scholar->id,
            ],
            [
                'scholarship_application_id' => $scholar->scholarship_application_id,
                'status' => 'Draft',
                'grades' => $this->gradeRows(),
                'computed_average' => 78.45,
                'submitted_at' => null,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultUserAttributes(bool $isStudent): array
    {
        return [
            'password' => 'password',
            'role' => $isStudent ? 'student' : 'officer',
            'status' => 'Active',
            'department' => $isStudent ? null : 'Scholarship Programs Office',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'city' => 'Bislig City',
            'province' => 'Surigao del Sur',
            'contact_number' => '09170000000',
            'campus' => $isStudent ? 'Main Campus' : null,
            'school_name' => $isStudent ? 'ScholarSync State University' : null,
            'semester' => $isStudent ? '1st Semester' : null,
            'academic_year' => $isStudent ? '2026-2027' : null,
            'enrollment_status' => $isStudent ? 'Currently Enrolled' : null,
            'father_name' => $isStudent ? 'Demo Father' : null,
            'mother_name' => $isStudent ? 'Demo Mother' : null,
            'guardian_name' => $isStudent ? 'Demo Guardian' : null,
            'parent_occupation' => $isStudent ? 'Self-employed' : null,
            'monthly_income' => $isStudent ? 'Below PHP 20,000' : null,
            'siblings' => $isStudent ? 3 : null,
            'studying_siblings' => $isStudent ? 1 : null,
            'income_bracket' => $isStudent ? 'Below PHP 20,000' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function programDefaults(): array
    {
        $requirements = [
            'Certificate of Enrollment / COE',
            'Certificate of Ratings',
            'Certificate of Indigency',
        ];

        return [
            'schedule' => [
                'opening' => now()->subWeeks(2)->format('M d, Y'),
                'deadline' => now()->addWeeks(2)->format('M d, Y'),
                'screening' => now()->addWeeks(3)->format('M d, Y'),
                'awarding' => now()->addMonth()->format('M d, Y'),
            ],
            'eligibility' => [
                'Must be a currently enrolled student',
                'Must meet the program academic or financial qualification',
                'Must submit complete and valid requirements',
            ],
            'requirements' => $requirements,
            'requirement_rules' => ScholarshipProgram::defaultRequirementRules($requirements),
            'scoring_criteria' => [
                'Academic performance',
                'Financial need',
                'Completeness of requirements',
            ],
            'renewal_rules' => [
                'Submit semester requirements when requested',
                'Maintain good academic standing',
                'Remain eligible under program rules',
            ],
            'published_at' => now()->subWeek(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function applicationTimeline(string $status, string $remarks): array
    {
        return [
            [
                'status' => 'Draft',
                'label' => 'Draft Created',
                'remarks' => 'Application draft was opened.',
                'date' => now()->subDays(30)->format('M d, Y'),
            ],
            [
                'status' => 'Submitted',
                'label' => 'Submitted',
                'remarks' => 'Application was submitted for review.',
                'date' => now()->subDays(20)->format('M d, Y'),
            ],
            [
                'status' => $status,
                'label' => $status,
                'remarks' => $remarks,
                'date' => now()->format('M d, Y'),
            ],
        ];
    }

    private function studentAddress(User $student): string
    {
        return collect([
            $student->address,
            $student->barangay,
            $student->city,
            $student->province,
        ])
            ->filter()
            ->implode(', ');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function semesterSubmissions(string $coeStatus, string $corStatus, string $gradesStatus): array
    {
        return [
            [
                'key' => 'coe',
                'requirement' => 'Certificate of Enrollment / COE',
                'status' => $coeStatus,
                'document' => $coeStatus === 'Missing' ? null : 'demo-documents/semester/coe.pdf',
                'requestedAt' => now()->subDays(20)->toISOString(),
                'submittedAt' => $coeStatus === 'Missing' ? null : now()->subDays(10)->toISOString(),
            ],
            [
                'key' => 'cor',
                'requirement' => 'Certificate of Registration / COR',
                'status' => $corStatus,
                'document' => $corStatus === 'Missing' ? null : 'demo-documents/semester/cor.pdf',
                'requestedAt' => now()->subDays(20)->toISOString(),
                'submittedAt' => $corStatus === 'Missing' ? null : now()->subDays(10)->toISOString(),
            ],
            [
                'key' => 'encoded-grades',
                'requirement' => 'Encoded Grades',
                'status' => $gradesStatus,
                'grades' => $gradesStatus === 'Missing' ? [] : $this->gradeRows(),
                'requestedAt' => now()->subDays(20)->toISOString(),
                'submittedAt' => $gradesStatus === 'Missing' ? null : now()->subDays(10)->toISOString(),
            ],
        ];
    }

    /**
     * @return array<int, array{subject: string, units: int, grade: float}>
     */
    private function gradeRows(): array
    {
        return [
            ['subject' => 'Data Structures', 'units' => 3, 'grade' => 91.50],
            ['subject' => 'Purposive Communication', 'units' => 3, 'grade' => 88.75],
            ['subject' => 'Ethics', 'units' => 3, 'grade' => 90.25],
        ];
    }
}
