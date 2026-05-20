<?php

namespace Database\Seeders;

use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScholarshipProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $programs = [
            ['code' => 'TDP', 'name' => 'Tulong Dunong Program', 'category' => 'Government Assistance', 'type' => 'Grant', 'slots' => 100, 'budget' => 3000000],
            ['code' => 'TES', 'name' => 'Tertiary Education Subsidy', 'category' => 'Government Subsidy', 'type' => 'Subsidy', 'slots' => 120, 'budget' => 6000000],
            ['code' => 'DOST', 'name' => 'DOST Scholarship', 'category' => 'Science and Technology', 'type' => 'Scholarship', 'slots' => 40, 'budget' => 4000000],
            ['code' => 'EAP', 'name' => 'Educational Assistance Program', 'category' => 'Educational Assistance', 'type' => 'Assistance', 'slots' => 80, 'budget' => 2000000],
            ['code' => 'SKEA', 'name' => 'SK Educational Assistance', 'category' => 'Local Youth Assistance', 'type' => 'Assistance', 'slots' => 60, 'budget' => 900000],
            ['code' => 'LGU-SCH', 'name' => 'LGU Scholarship', 'category' => 'Local Government Scholarship', 'type' => 'Scholarship', 'slots' => 50, 'budget' => 1500000],
        ];

        foreach ($programs as $index => $program) {
            ScholarshipProgram::create([
                'name' => $program['name'],
                'provider' => $program['code'],
                'category' => $program['category'],
                'type' => $program['type'],
                'description' => "{$program['name']} ({$program['code']}) supports qualified students through scholarship or educational assistance benefits.",
                'eligibility_summary' => 'Qualified enrolled students who meet program-specific academic, residency, and documentary requirements.',
                'status' => $index < 4 ? 'Open' : 'Closed',
                'slots' => $program['slots'],
                'used_slots' => 0,
                'budget' => $program['budget'],
                'schedule' => [
                    'opening' => 'May 01, 2026',
                    'deadline' => 'June 15, 2026',
                    'screening' => 'June 20, 2026',
                    'awarding' => 'July 01, 2026',
                ],
                'eligibility' => [
                    'Must be a currently enrolled student',
                    'Must meet the program academic or financial qualification',
                    'Must submit complete and valid requirements',
                ],
                'requirements' => [
                    'Certificate of Registration',
                    'Grades Transcript',
                    'Certificate of Indigency',
                ],
                'scoring_criteria' => [
                    'Academic standing',
                    'Financial need',
                    'Program eligibility fit',
                ],
                'renewal_rules' => [
                    'Submit semester requirements when requested',
                    'Maintain good academic standing',
                    'Remain eligible under program rules',
                ],
                'assigned_admin_ids' => [$adminUser->id],
                'published_at' => $index < 4 ? now()->subWeeks(2) : null,
            ]);
        }
    }
}
