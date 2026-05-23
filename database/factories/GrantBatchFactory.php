<?php

namespace Database\Factories;

use App\Models\GrantBatch;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrantBatch>
 */
class GrantBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $claimingStartDate = fake()->dateTimeBetween('now', '+2 weeks');
        $claimingEndDate = (clone $claimingStartDate)->modify('+4 days');

        return [
            'scholarship_program_id' => ScholarshipProgram::factory()->published(),
            'created_by_id' => User::factory()->headOfficer(),
            'title' => fake()->sentence(4),
            'semester' => fake()->randomElement(['1st Semester', '2nd Semester', 'Summer']),
            'school_year' => '2025-2026',
            'amount' => fake()->numberBetween(5000, 20000),
            'claiming_start_date' => $claimingStartDate,
            'claiming_end_date' => $claimingEndDate,
            'venue' => 'Scholarship Programs Office',
            'daily_limit' => fake()->numberBetween(20, 60),
            'remarks' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['Draft', 'Open']),
        ];
    }

    /**
     * Mark the grant batch as open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Open',
        ]);
    }
}
