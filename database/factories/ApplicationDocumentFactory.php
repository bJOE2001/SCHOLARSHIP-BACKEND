<?php

namespace Database\Factories;

use App\Models\ApplicationDocument;
use App\Models\ScholarshipApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationDocument>
 */
class ApplicationDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(['Pending', 'Accepted', 'Rejected', 'Missing']);

        return [
            'scholarship_application_id' => ScholarshipApplication::factory(),
            'name' => fake()->randomElement([
                'Certificate of Enrollment / COE',
                'Certificate of Ratings',
                'Certificate of Indigency',
                'Good Moral Certificate',
            ]),
            'type' => fake()->randomElement(['PDF', 'JPG', 'PNG']),
            'path' => 'applications/'.fake()->unique()->numerify('DOC-#######').'.pdf',
            'status' => $status,
            'remarks' => fake()->sentence(8),
            'uploaded_by_id' => User::factory(),
            'validated_by_id' => null,
            'uploaded_at' => now()->subDays(fake()->numberBetween(0, 5)),
        ];
    }

    /**
     * Mark the document as accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Accepted',
            'remarks' => 'Validated and accepted.',
        ]);
    }

    /**
     * Mark the document as rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Rejected',
            'remarks' => 'Needs clearer or updated file copy.',
        ]);
    }

    /**
     * Mark the document as missing.
     */
    public function missing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Missing',
            'remarks' => 'Not yet uploaded.',
        ]);
    }
}
