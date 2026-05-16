<?php

namespace Database\Factories;

use App\Models\ScholarshipNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScholarshipNotification>
 */
class ScholarshipNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['status', 'warning', 'success', 'insight', 'task', 'admin']);

        return [
            'user_id' => User::factory(),
            'role' => 'student',
            'type' => $type,
            'title' => fake()->sentence(5),
            'message' => fake()->sentence(12),
            'notified_at' => now()->subHours(fake()->numberBetween(1, 72)),
            'read_at' => fake()->boolean(35) ? now()->subHours(fake()->numberBetween(1, 12)) : null,
            'payload' => [
                'origin' => 'system',
            ],
        ];
    }

    /**
     * Target an administrator notification.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'type' => 'admin',
        ]);
    }

    /**
     * Target a student notification.
     */
    public function student(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'student',
        ]);
    }

    /**
     * Mark the notification as read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }
}
