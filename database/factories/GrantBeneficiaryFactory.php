<?php

namespace Database\Factories;

use App\Models\GrantBatch;
use App\Models\GrantBeneficiary;
use App\Models\Scholar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrantBeneficiary>
 */
class GrantBeneficiaryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $scholar = Scholar::factory();

        return [
            'grant_batch_id' => GrantBatch::factory()->open(),
            'scholar_id' => $scholar,
            'user_id' => User::factory(),
            'released_by_id' => null,
            'scholar_identifier' => fake()->unique()->numerify('SCH-2026-####'),
            'scholar_name' => fake()->name(),
            'barangay' => fake()->city(),
            'course' => fake()->randomElement(['BSIT', 'BSA', 'BSBA', 'BSED']),
            'amount' => fake()->numberBetween(5000, 20000),
            'assigned_claim_date' => now()->addDays(fake()->numberBetween(1, 5)),
            'time_slot' => fake()->randomElement(['8:00 AM - 12:00 PM', '1:00 PM - 5:00 PM']),
            'claim_status' => 'For Claiming',
            'notified_at' => null,
            'claimed_at' => null,
            'released_by_name' => null,
            'reference_number' => fake()->unique()->bothify('GRNT-######-????'),
            'claim_method' => null,
            'release_remarks' => null,
        ];
    }

    /**
     * Mark the beneficiary as claimed.
     */
    public function claimed(): static
    {
        return $this->state(fn (array $attributes) => [
            'released_by_id' => User::factory()->officer(),
            'claim_status' => 'Claimed',
            'claimed_at' => now(),
            'released_by_name' => fake()->name(),
            'claim_method' => 'Cash',
        ]);
    }
}
