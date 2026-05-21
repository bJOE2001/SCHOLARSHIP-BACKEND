<?php

namespace Database\Factories;

use App\Models\ScholarshipProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScholarshipProgram>
 */
class ScholarshipProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slots = fake()->numberBetween(10, 50);
        $usedSlots = fake()->numberBetween(0, max($slots - 1, 0));
        $requirements = [
            'Certificate of Enrollment / COE',
            'Certificate of Ratings',
            'Certificate of Indigency',
        ];

        return [
            'name' => fake()->randomElement([
                'Merit Scholars Grant',
                'STEM Excellence Scholarship',
                'Community Service Grant',
                'Academic Support Fund',
            ]),
            'provider' => fake()->company().' Foundation',
            'category' => fake()->randomElement([
                'Academic',
                'Need-Based',
                'Merit',
                'Community',
            ]),
            'type' => fake()->randomElement([
                'Scholarship',
                'Grant',
                'Stipend',
            ]),
            'description' => fake()->paragraph(3),
            'eligibility_summary' => fake()->sentence(14),
            'status' => fake()->randomElement(['Open', 'Closing Soon', 'Closed']),
            'slots' => $slots,
            'used_slots' => $usedSlots,
            'budget' => fake()->numberBetween(500000, 5000000),
            'schedule' => [
                'opening' => fake()->date('M d, Y'),
                'deadline' => fake()->date('M d, Y'),
                'screening' => fake()->date('M d, Y'),
                'awarding' => fake()->date('M d, Y'),
            ],
            'eligibility' => [
                'Must be a currently enrolled student',
                'Maintain the required GPA',
                'Submit all documentary requirements',
            ],
            'requirements' => $requirements,
            'requirement_rules' => ScholarshipProgram::defaultRequirementRules($requirements),
            'scoring_criteria' => [
                'Academic performance',
                'Financial need',
                'Leadership and service',
            ],
            'renewal_rules' => [
                'Maintain the minimum GPA',
                'Submit requirements every semester',
                'Follow scholarship code of conduct',
            ],
            'published_at' => null,
        ];
    }

    /**
     * Mark the program as open to applicants.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Open',
            'published_at' => now(),
        ]);
    }

    /**
     * Mark the program as closing soon.
     */
    public function closingSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Closing Soon',
            'published_at' => now(),
        ]);
    }

    /**
     * Mark the program as closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Closed',
        ]);
    }
}
