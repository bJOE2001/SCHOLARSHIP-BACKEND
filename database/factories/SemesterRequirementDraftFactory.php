<?php

namespace Database\Factories;

use App\Models\Scholar;
use App\Models\SemesterRequirementDraft;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SemesterRequirementDraft>
 */
class SemesterRequirementDraftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scholar_id' => Scholar::factory(),
            'scholarship_application_id' => null,
            'status' => 'Draft',
            'grades' => [
                [
                    'id' => 'draft-1',
                    'code' => 'MATH 101',
                    'subjectCode' => 'MATH 101',
                    'name' => 'College Algebra',
                    'subjectName' => 'College Algebra',
                    'units' => 3,
                    'grade' => 1.75,
                ],
            ],
            'computed_average' => 1.75,
            'submitted_at' => null,
        ];
    }
}
