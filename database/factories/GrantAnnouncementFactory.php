<?php

namespace Database\Factories;

use App\Models\GrantAnnouncement;
use App\Models\GrantBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrantAnnouncement>
 */
class GrantAnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grant_batch_id' => GrantBatch::factory()->open(),
            'created_by_id' => User::factory()->headOfficer(),
            'title' => fake()->sentence(5),
            'message' => fake()->sentence(14),
            'program_name' => fake()->randomElement([
                'Merit Scholars Grant',
                'STEM Excellence Scholarship',
                'Community Service Grant',
            ]),
            'semester' => fake()->randomElement(['1st Semester', '2nd Semester']),
            'school_year' => '2025-2026',
            'venue' => 'Scholarship Programs Office',
            'total_beneficiaries' => fake()->numberBetween(10, 80),
            'created_by_name' => fake()->name(),
        ];
    }
}
