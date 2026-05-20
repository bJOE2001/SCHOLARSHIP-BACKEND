<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $studentId = fake()->unique()->numerify('STU-2026-####');
        $gpa = fake()->randomFloat(2, 1.5, 4.0);

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => 'student',
            'status' => 'Active',
            'avatar' => null,
            'department' => null,
            'student_id' => $studentId,
            'birth_date' => fake()->dateTimeBetween('-25 years', '-18 years'),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'address' => fake()->streetAddress(),
            'barangay' => fake()->word(),
            'city' => fake()->city(),
            'province' => fake()->state(),
            'contact_number' => fake()->numerify('09#########'),
            'campus' => fake()->city().' Campus',
            'school_name' => fake()->company().' University',
            'course' => fake()->randomElement(['BSIT', 'BSA', 'BSBA', 'BSED']),
            'year_level' => fake()->randomElement(['1st Year', '2nd Year', '3rd Year', '4th Year']),
            'semester' => fake()->randomElement(['1st Semester', '2nd Semester']),
            'academic_year' => '2025-2026',
            'gpa' => $gpa,
            'family_income' => fake()->numberBetween(12000, 90000),
            'enrollment_status' => 'Currently Enrolled',
            'academic_awards' => fake()->optional()->sentence(4),
            'father_name' => fake()->name(),
            'mother_name' => fake()->name(),
            'guardian_name' => fake()->name(),
            'parent_occupation' => fake()->jobTitle(),
            'monthly_income' => fake()->randomElement([
                'Below PHP 20,000',
                'PHP 20,000 - PHP 39,999',
                'PHP 40,000 - PHP 59,999',
                'PHP 60,000 and above',
            ]),
            'siblings' => fake()->numberBetween(0, 6),
            'studying_siblings' => fake()->numberBetween(0, 4),
            'income_bracket' => fake()->randomElement([
                'Below PHP 20,000',
                'PHP 20,000 - PHP 39,999',
                'PHP 40,000 - PHP 59,999',
                'PHP 60,000 and above',
            ]),
            'assigned_program_ids' => [],
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Mark the user as an administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'status' => 'Active',
            'department' => fake()->randomElement([
                'Scholarship Administration',
                'Student Services',
                'Academic Affairs',
            ]),
            'student_id' => null,
            'school_name' => null,
            'assigned_program_ids' => [],
        ]);
    }

    /**
     * Mark the user as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Inactive',
        ]);
    }
}
