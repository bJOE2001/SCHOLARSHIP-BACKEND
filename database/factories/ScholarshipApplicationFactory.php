<?php

namespace Database\Factories;

use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScholarshipApplication>
 */
class ScholarshipApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement([
            'Draft',
            'Submitted',
            'Under Review',
            'Needs Revision',
            'Accepted',
            'Rejected',
            'Enrollment Verified',
            'Active Scholar',
            'Renewal Pending',
            'Renewed',
        ]);
        $riskLabel = fake()->randomElement(['Stable', 'Borderline', 'At Risk', 'Critical']);

        return [
            'scholarship_program_id' => ScholarshipProgram::factory(),
            'applicant_id' => User::factory(),
            'application_no' => fake()->unique()->bothify('APP-2026-#####'),
            'status' => $status,
            'risk_label' => $riskLabel,
            'score' => fake()->numberBetween(55, 100),
            'progress' => fake()->numberBetween(10, 100),
            'remarks' => fake()->sentence(10),
            'next_action' => fake()->sentence(8),
            'missing_requirements' => fake()->boolean()
                ? ['Certificate of Registration', 'Grades Transcript']
                : [],
            'timeline' => [
                [
                    'status' => 'Draft',
                    'label' => 'Draft Created',
                    'remarks' => 'Application draft opened.',
                    'date' => now()->subDays(8)->format('M d, Y'),
                ],
                [
                    'status' => $status,
                    'label' => $status,
                    'remarks' => fake()->sentence(10),
                    'date' => now()->format('M d, Y'),
                ],
            ],
            'submitted_at' => now()->subDays(fake()->numberBetween(1, 14)),
            'reviewed_at' => in_array($status, ['Under Review', 'Needs Revision', 'Accepted', 'Rejected'], true)
                ? now()->subDays(fake()->numberBetween(0, 5))
                : null,
            'reviewed_by_id' => null,
        ];
    }

    /**
     * Mark the application as submitted.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Submitted',
            'progress' => 35,
            'risk_label' => 'Stable',
        ]);
    }

    /**
     * Mark the application as under review.
     */
    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Under Review',
            'progress' => 45,
            'risk_label' => 'Borderline',
        ]);
    }

    /**
     * Mark the application as approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Accepted',
            'progress' => 90,
            'risk_label' => 'Stable',
            'missing_requirements' => [],
        ]);
    }

    /**
     * Mark the application as an active scholar record.
     */
    public function activeScholar(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Active Scholar',
            'progress' => 100,
            'risk_label' => 'Stable',
            'missing_requirements' => [],
        ]);
    }

    /**
     * Mark the application as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Rejected',
            'progress' => 100,
            'risk_label' => 'Critical',
        ]);
    }
}
