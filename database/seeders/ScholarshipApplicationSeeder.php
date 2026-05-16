<?php

namespace Database\Seeders;

use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScholarshipApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $studentOne = User::query()->where('email', 'student1@example.com')->firstOrFail();
        $studentTwo = User::query()->where('email', 'student2@example.com')->firstOrFail();
        $studentThree = User::query()->where('email', 'student3@example.com')->firstOrFail();
        $programOne = ScholarshipProgram::query()->where('name', 'Merit Scholars Grant')->firstOrFail();
        $programTwo = ScholarshipProgram::query()->where('name', 'STEM Excellence Scholarship')->firstOrFail();
        $programThree = ScholarshipProgram::query()->where('name', 'Community Service Grant')->firstOrFail();
        $programFour = ScholarshipProgram::query()->where('name', 'Academic Support Fund')->firstOrFail();
        $adminUser = User::query()->where('email', 'admin@example.com')->firstOrFail();

        ScholarshipApplication::create([
            'scholarship_program_id' => $programOne->id,
            'applicant_id' => $studentOne->id,
            'application_no' => 'APP-2026-10001',
            'status' => 'Submitted',
            'risk_label' => 'Stable',
            'score' => 82,
            'progress' => 35,
            'remarks' => 'Initial application submitted.',
            'next_action' => 'Screen the application and verify documents.',
            'missing_requirements' => $programOne->requirements,
            'timeline' => [
                [
                    'status' => 'Draft',
                    'label' => 'Draft Created',
                    'remarks' => 'Student started the application.',
                    'date' => 'May 10, 2026',
                ],
                [
                    'status' => 'Submitted',
                    'label' => 'Submitted',
                    'remarks' => 'Initial application submitted.',
                    'date' => 'May 12, 2026',
                ],
            ],
            'submitted_at' => now()->subDays(4),
        ]);

        ScholarshipApplication::create([
            'scholarship_program_id' => $programOne->id,
            'applicant_id' => $studentTwo->id,
            'application_no' => 'APP-2026-10002',
            'status' => 'Under Review',
            'risk_label' => 'Borderline',
            'score' => 76,
            'progress' => 50,
            'remarks' => 'Document validation in progress.',
            'next_action' => 'Review uploaded files and compute ranking.',
            'missing_requirements' => ['Certificate of Indigency'],
            'timeline' => [
                [
                    'status' => 'Draft',
                    'label' => 'Draft Created',
                    'remarks' => 'Student started the application.',
                    'date' => 'May 08, 2026',
                ],
                [
                    'status' => 'Submitted',
                    'label' => 'Submitted',
                    'remarks' => 'Application submitted for review.',
                    'date' => 'May 09, 2026',
                ],
                [
                    'status' => 'Under Review',
                    'label' => 'Under Review',
                    'remarks' => 'Under officer review.',
                    'date' => 'May 14, 2026',
                ],
            ],
            'submitted_at' => now()->subDays(6),
            'reviewed_at' => now()->subDays(1),
            'reviewed_by_id' => $adminUser->id,
        ]);

        ScholarshipApplication::create([
            'scholarship_program_id' => $programTwo->id,
            'applicant_id' => $studentOne->id,
            'application_no' => 'APP-2026-10003',
            'status' => 'Accepted',
            'risk_label' => 'Stable',
            'score' => 91,
            'progress' => 90,
            'remarks' => 'Approved for enrollment verification.',
            'next_action' => 'Verify enrollment and set up scholar monitoring.',
            'missing_requirements' => [],
            'timeline' => [
                [
                    'status' => 'Submitted',
                    'label' => 'Submitted',
                    'remarks' => 'Application submitted.',
                    'date' => 'April 27, 2026',
                ],
                [
                    'status' => 'Accepted',
                    'label' => 'Accepted',
                    'remarks' => 'Application approved.',
                    'date' => 'May 02, 2026',
                ],
            ],
            'submitted_at' => now()->subWeeks(3),
            'reviewed_at' => now()->subWeeks(2),
            'reviewed_by_id' => $adminUser->id,
        ]);

        ScholarshipApplication::create([
            'scholarship_program_id' => $programThree->id,
            'applicant_id' => $studentThree->id,
            'application_no' => 'APP-2026-10004',
            'status' => 'Active Scholar',
            'risk_label' => 'Stable',
            'score' => 95,
            'progress' => 100,
            'remarks' => 'Already enrolled as an active scholar.',
            'next_action' => 'Continue compliance monitoring.',
            'missing_requirements' => [],
            'timeline' => [
                [
                    'status' => 'Submitted',
                    'label' => 'Submitted',
                    'remarks' => 'Application submitted.',
                    'date' => 'March 30, 2026',
                ],
                [
                    'status' => 'Accepted',
                    'label' => 'Accepted',
                    'remarks' => 'Application approved.',
                    'date' => 'April 08, 2026',
                ],
                [
                    'status' => 'Active Scholar',
                    'label' => 'Active Scholar',
                    'remarks' => 'Scholar is now active.',
                    'date' => 'April 20, 2026',
                ],
            ],
            'submitted_at' => now()->subMonth(),
            'reviewed_at' => now()->subWeeks(3),
            'reviewed_by_id' => $adminUser->id,
        ]);

        ScholarshipApplication::create([
            'scholarship_program_id' => $programFour->id,
            'applicant_id' => $studentThree->id,
            'application_no' => 'APP-2026-10005',
            'status' => 'Needs Revision',
            'risk_label' => 'At Risk',
            'score' => 68,
            'progress' => 60,
            'remarks' => 'Missing income supporting documents.',
            'next_action' => 'Request updated documents from the student.',
            'missing_requirements' => ['Income Certificate'],
            'timeline' => [
                [
                    'status' => 'Draft',
                    'label' => 'Draft Created',
                    'remarks' => 'Student started the application.',
                    'date' => 'April 02, 2026',
                ],
                [
                    'status' => 'Submitted',
                    'label' => 'Submitted',
                    'remarks' => 'Application submitted.',
                    'date' => 'April 05, 2026',
                ],
                [
                    'status' => 'Needs Revision',
                    'label' => 'Needs Revision',
                    'remarks' => 'Missing income supporting documents.',
                    'date' => 'April 12, 2026',
                ],
            ],
            'submitted_at' => now()->subWeeks(5),
            'reviewed_at' => now()->subWeeks(4),
            'reviewed_by_id' => $adminUser->id,
        ]);

        ScholarshipProgram::query()->get()->each(function (ScholarshipProgram $program): void {
            $usedSlots = ScholarshipApplication::query()
                ->where('scholarship_program_id', $program->id)
                ->whereIn('status', ['Accepted', 'Enrollment Verified', 'Active Scholar', 'Renewal Pending', 'Renewed'])
                ->count();

            $program->update([
                'used_slots' => $usedSlots,
            ]);
        });
    }
}
