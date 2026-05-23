<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scholarship_program_id' => ScholarshipProgram::factory()->published(),
            'created_by_id' => User::factory()->headOfficer(),
            'title' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'pin' => fake()->boolean(),
            'status' => 'Published',
            'published_at' => now(),
        ];
    }
}
