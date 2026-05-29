<?php

namespace Database\Seeders;

use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScholarshipProgramSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $headOfficer = User::query()->where('email', 'head.officer@example.com')->firstOrFail();
        $tdpOfficer = User::query()->where('email', 'tdp.officer@example.com')->firstOrFail();
        $meritOfficer = User::query()->where('email', 'merit.officer@example.com')->firstOrFail();
        $programs = [
            ['code' => 'TDP', 'name' => 'Tulong Dunong Program', 'category' => 'Government Assistance', 'type' => 'Grant', 'slots' => 100, 'budget' => 3000000],
            ['code' => 'MERIT', 'name' => 'Merit Scholars Grant', 'category' => 'Academic Merit', 'type' => 'Scholarship', 'slots' => 45, 'budget' => 1800000],
        ];
        $programOfficers = [
            'TDP' => $tdpOfficer,
            'MERIT' => $meritOfficer,
        ];

        foreach ($programs as $index => $program) {
            $scholarshipProgram = ScholarshipProgram::query()->updateOrCreate([
                'provider' => $program['code'],
            ], [
                'name' => $program['name'],
                'provider' => $program['code'],
                'category' => $program['category'],
                'type' => $program['type'],
                'description' => "{$program['name']} ({$program['code']}) supports qualified students through scholarship or educational assistance benefits.",
                'eligibility_summary' => 'Qualified enrolled students who meet program-specific academic, residency, and documentary requirements.',
                'status' => $index < 4 ? 'Open' : 'Closed',
                'slots' => $program['slots'],
                'used_slots' => 0,
                'budget' => $program['budget'],
                'schedule' => [
                    'opening' => 'May 01, 2026',
                    'deadline' => 'June 15, 2026',
                ],
                'eligibility' => [
                    'Must be a currently enrolled student',
                    'Must meet the program academic or financial qualification',
                    'Must submit complete and valid requirements',
                ],
                'requirements' => $this->applicationRequirements(),
                'requirement_rules' => $this->applicationRequirementRules($program['code']),
                'scoring_criteria' => [
                    'Academic standing',
                    'Financial need',
                    'Program eligibility fit',
                ],
                'renewal_rules' => [
                    'Submit semester requirements when requested',
                    'Maintain good academic standing',
                    'Remain eligible under program rules',
                ],
                'published_at' => $index < 4 ? now()->subWeeks(2) : null,
            ]);

            $scholarshipProgram->assignedOfficers()->sync([$programOfficers[$program['code']]->id]);
        }

        $headOfficer->assignedPrograms()->sync([]);
        $tdpOfficer->assignedPrograms()->sync(
            ScholarshipProgram::query()
                ->where('provider', 'TDP')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );
        $meritOfficer->assignedPrograms()->sync(
            ScholarshipProgram::query()
                ->where('provider', 'MERIT')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function applicationRequirements(): array
    {
        return [
            'Certificate of Enrollment / COE',
            'Certificate of Ratings',
            'Certificate of Indigency',
        ];
    }

    /**
     * @return array<int, array{name: string, stage: string, isRequired: bool, allowToFollow: bool}>
     */
    private function applicationRequirementRules(string $provider): array
    {
        return array_map(
            static function (array $rule) use ($provider): array {
                if ($provider === 'SKEA' && $rule['name'] !== 'Certificate of Indigency') {
                    $rule['allowToFollow'] = true;
                }

                return $rule;
            },
            ScholarshipProgram::defaultRequirementRules($this->applicationRequirements()),
        );
    }
}
