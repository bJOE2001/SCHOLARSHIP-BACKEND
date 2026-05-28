<?php

namespace Database\Factories;

use App\Models\Scholar;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scholar>
 */
class ScholarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $riskLabel = fake()->randomElement(['Stable', 'Borderline', 'At Risk', 'Critical']);

        return [
            'user_id' => User::factory(),
            'scholarship_program_id' => ScholarshipProgram::factory()->published(),
            'scholarship_application_id' => ScholarshipApplication::factory()->activeScholar(),
            'scholar_id' => fake()->unique()->numerify('SCH-2026-####'),
            'name' => fake()->name(),
            'avatar' => null,
            'program' => fake()->randomElement([
                'Merit Scholars Grant',
                'STEM Excellence Scholarship',
                'Community Service Grant',
                'Academic Support Fund',
            ]),
            'course' => fake()->randomElement(['BSIT', 'BSA', 'BSBA', 'BSED']),
            'year_level' => fake()->randomElement(['1st Year', '2nd Year', '3rd Year', '4th Year']),
            'school' => fake()->company().' University',
            'gender' => fake()->randomElement(['Male', 'Female']),
            'birthdate' => fake()->dateTimeBetween('-25 years', '-18 years'),
            'address' => fake()->address(),
            'contact_number' => fake()->numerify('09#########'),
            'email' => fake()->safeEmail(),
            'gpa' => fake()->randomFloat(2, 75, 100),
            'enrollment_status' => 'Enrolled and Verified',
            'academic_year' => '2025-2026',
            'semester' => fake()->randomElement(['1st Semester', '2nd Semester']),
            'scholarship_status' => 'Active',
            'renewal_status' => fake()->randomElement(['Active', 'Renewal Pending', 'Under Evaluation', 'Renewed']),
            'date_approved' => now()->subMonths(fake()->numberBetween(1, 6)),
            'duration' => '1 Academic Year',
            'compliance_status' => fake()->randomElement([
                'Complete',
                'Pending Review',
                'Missing Requirements',
                'Non-Compliant',
            ]),
            'compliance_rate' => fake()->numberBetween(70, 100),
            'risk_label' => $riskLabel,
            'risk_reason' => fake()->sentence(10),
            'recommended_action' => fake()->sentence(8),
            'submissions' => [
                [
                    'requirement' => 'Certificate of Ratings',
                    'status' => fake()->randomElement(['Submitted', 'Accepted', 'Pending']),
                ],
                [
                    'requirement' => 'Certificate of Indigency',
                    'status' => fake()->randomElement(['Submitted', 'Accepted', 'Missing']),
                ],
            ],
        ];
    }

    /**
     * Mark the scholar as stable.
     */
    public function stable(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_label' => 'Stable',
            'compliance_status' => 'Complete',
            'compliance_rate' => 100,
        ]);
    }

    /**
     * Mark the scholar as at risk.
     */
    public function atRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_label' => 'At Risk',
            'compliance_status' => 'Missing Requirements',
            'compliance_rate' => 68,
        ]);
    }
}
