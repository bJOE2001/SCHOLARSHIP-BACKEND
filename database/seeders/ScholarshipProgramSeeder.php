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

        ScholarshipProgram::create([
            'name' => 'Merit Scholars Grant',
            'provider' => 'ScholarSync Foundation',
            'category' => 'Merit',
            'type' => 'Scholarship',
            'description' => 'Supports high-performing students with demonstrated academic excellence and leadership.',
            'eligibility_summary' => 'Currently enrolled students with strong grades and active campus service.',
            'status' => 'Open',
            'slots' => 30,
            'used_slots' => 12,
            'budget' => 1500000,
            'schedule' => [
                'opening' => 'May 01, 2026',
                'deadline' => 'June 15, 2026',
                'screening' => 'June 20, 2026',
                'awarding' => 'July 01, 2026',
            ],
            'eligibility' => [
                'Must be a currently enrolled student',
                'Maintain the minimum GPA requirement',
                'Submit all requested documents',
            ],
            'requirements' => [
                'Certificate of Registration',
                'Grades Transcript',
                'Certificate of Indigency',
            ],
            'scoring_criteria' => [
                'Academic performance',
                'Leadership and service',
                'Financial need',
            ],
            'renewal_rules' => [
                'Maintain the required GPA',
                'Submit semester requirements on time',
                'Remain in good standing',
            ],
            'assigned_admin_ids' => [$adminUser->id],
            'published_at' => now()->subWeeks(2),
        ]);

        ScholarshipProgram::create([
            'name' => 'STEM Excellence Scholarship',
            'provider' => 'ScholarSync Foundation',
            'category' => 'Academic',
            'type' => 'Grant',
            'description' => 'Awards students enrolled in STEM courses with strong academic records.',
            'eligibility_summary' => 'Science, technology, engineering, and math students with above-average grades.',
            'status' => 'Closing Soon',
            'slots' => 20,
            'used_slots' => 8,
            'budget' => 1000000,
            'schedule' => [
                'opening' => 'April 10, 2026',
                'deadline' => 'May 25, 2026',
                'screening' => 'May 28, 2026',
                'awarding' => 'June 10, 2026',
            ],
            'eligibility' => [
                'Must be enrolled in a STEM program',
                'Must maintain the minimum GPA requirement',
                'Must not have disciplinary sanctions',
            ],
            'requirements' => [
                'Certificate of Registration',
                'Grades Transcript',
                'Recommendation Letter',
            ],
            'scoring_criteria' => [
                'GPA',
                'Course alignment',
                'Interview performance',
            ],
            'renewal_rules' => [
                'Reapply every school year',
                'Keep the required GPA',
                'Submit renewal documents on time',
            ],
            'assigned_admin_ids' => [$adminUser->id],
            'published_at' => now()->subMonth(),
        ]);

        ScholarshipProgram::create([
            'name' => 'Community Service Grant',
            'provider' => 'ScholarSync Foundation',
            'category' => 'Community',
            'type' => 'Stipend',
            'description' => 'Recognizes students who actively contribute to their community and campus organizations.',
            'eligibility_summary' => 'Students with verified community engagement and moderate household income.',
            'status' => 'Open',
            'slots' => 15,
            'used_slots' => 5,
            'budget' => 600000,
            'schedule' => [
                'opening' => 'May 05, 2026',
                'deadline' => 'June 20, 2026',
                'screening' => 'June 25, 2026',
                'awarding' => 'July 08, 2026',
            ],
            'eligibility' => [
                'Must be active in a student or community organization',
                'Must have no unresolved conduct cases',
                'Must be currently enrolled',
            ],
            'requirements' => [
                'Certificate of Registration',
                'Community Service Certificate',
                'Grades Transcript',
            ],
            'scoring_criteria' => [
                'Community involvement',
                'Academic standing',
                'Need assessment',
            ],
            'renewal_rules' => [
                'Continue community service participation',
                'Keep the minimum GPA',
                'Submit updated documents every semester',
            ],
            'assigned_admin_ids' => [$adminUser->id],
            'published_at' => now()->subDays(10),
        ]);

        ScholarshipProgram::create([
            'name' => 'Academic Support Fund',
            'provider' => 'ScholarSync Foundation',
            'category' => 'Need-Based',
            'type' => 'Scholarship',
            'description' => 'Helps students from low-income households finish the current academic year.',
            'eligibility_summary' => 'Need-based support for students with verified financial constraints.',
            'status' => 'Closed',
            'slots' => 25,
            'used_slots' => 20,
            'budget' => 2000000,
            'schedule' => [
                'opening' => 'January 10, 2026',
                'deadline' => 'March 30, 2026',
                'screening' => 'April 05, 2026',
                'awarding' => 'April 18, 2026',
            ],
            'eligibility' => [
                'Must demonstrate financial need',
                'Must be currently enrolled',
                'Must maintain satisfactory academic progress',
            ],
            'requirements' => [
                'Certificate of Registration',
                'Grades Transcript',
                'Income Certificate',
            ],
            'scoring_criteria' => [
                'Financial need',
                'Academic standing',
                'Program priority',
            ],
            'renewal_rules' => [
                'Provide updated income documents every term',
                'Maintain the required GPA',
                'Continue enrollment in the same school year',
            ],
            'assigned_admin_ids' => [$adminUser->id],
            'published_at' => now()->subMonths(2),
        ]);
    }
}
