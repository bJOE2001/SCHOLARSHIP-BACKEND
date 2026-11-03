<?php

namespace App\Console\Commands;

use App\Models\Scholar;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipProgram;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedMlCohortsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scholarship:seed-ml-cohorts {--months=6 : Number of lookback months} {--per-month=6 : Applicants per month per program}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed realistic multi-month cohorts for Random Forest forecasting and Decision Tree risk detection.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lookbackMonths = (int) $this->option('months');
        $perMonth = (int) $this->option('per-month');

        $this->info("Seeding ML cohorts: {$lookbackMonths} months history, ~{$perMonth} applications/month/program...");

        $programs = ScholarshipProgram::query()->where('status', 'Open')->get();

        if ($programs->isEmpty()) {
            $programs = collect([
                ScholarshipProgram::query()->updateOrCreate(['name' => 'Tulong Dunong Program'], [
                    'provider' => 'TDP',
                    'category' => 'Government Assistance',
                    'type' => 'Grant',
                    'description' => 'Supports qualified enrolled students through semester-based educational assistance.',
                    'eligibility_summary' => 'Open to enrolled students with verified financial need.',
                    'status' => 'Open',
                    'slots' => 100,
                    'used_slots' => 15,
                    'budget' => 3000000,
                    'maintaining_grade' => 83.0,
                    'published_at' => now()->subYears(2),
                ]),
                ScholarshipProgram::query()->updateOrCreate(['name' => 'Merit Scholars Grant'], [
                    'provider' => 'MERIT',
                    'category' => 'Academic Merit',
                    'type' => 'Scholarship',
                    'description' => 'Rewards high-performing students with strong grades.',
                    'eligibility_summary' => 'Must maintain at least 88% GWA.',
                    'status' => 'Open',
                    'slots' => 50,
                    'used_slots' => 10,
                    'budget' => 2000000,
                    'maintaining_grade' => 88.0,
                    'published_at' => now()->subYears(2),
                ]),
                ScholarshipProgram::query()->updateOrCreate(['name' => 'STEM Excellence Scholarship'], [
                    'provider' => 'STEM',
                    'category' => 'Science and Technology',
                    'type' => 'Scholarship',
                    'description' => 'Funds students in science, technology, and engineering.',
                    'eligibility_summary' => 'Enrolled in STEM program with 85% GWA.',
                    'status' => 'Open',
                    'slots' => 40,
                    'used_slots' => 8,
                    'budget' => 2500000,
                    'maintaining_grade' => 85.0,
                    'published_at' => now()->subYears(2),
                ]),
            ]);
        }

        $now = now();
        $totalApps = 0;
        $totalScholars = 0;

        DB::transaction(function () use ($programs, $lookbackMonths, $perMonth, $now, &$totalApps, &$totalScholars): void {
            $courses = ['BSIT', 'BSCS', 'BS Civil Engineering', 'BS Nursing', 'BS Accountancy', 'BSEd'];

            foreach ($programs as $program) {
                for ($m = $lookbackMonths; $m >= 0; $m--) {
                    $monthDate = $now->copy()->subMonths($m)->startOfMonth();
                    $isCurrentMonth = ($m === 0);
                    $countForThisMonth = $isCurrentMonth ? max(2, (int) round($perMonth * ($now->day / $now->daysInMonth))) : $perMonth;

                    for ($i = 0; $i < $countForThisMonth; $i++) {
                        $dayOffset = $isCurrentMonth ? min($now->day - 1, rand(1, 20)) : rand(2, 26);
                        $submittedDate = $monthDate->copy()->addDays($dayOffset)->setTime(rand(8, 17), rand(0, 59));

                        $gpa = round(74.0 + (rand(0, 240) / 10.0), 1); // 74.0 to 98.0
                        $income = rand(80000, 550000);
                        $course = $courses[array_rand($courses)];

                        $uniqueKey = sprintf('student.%d.%d.%d@scholarsync.local', $program->id, $m, $i);
                        $user = User::query()->firstOrCreate(
                            ['email' => $uniqueKey],
                            [
                                'name' => fake()->name(),
                                'role' => 'student',
                                'status' => 'active',
                                'course' => $course,
                                'year_level' => '3rd Year',
                                'school' => 'University State College',
                                'gpa' => $gpa,
                                'annual_income' => $income,
                                'password' => bcrypt('password'),
                            ]
                        );

                        $status = match (true) {
                            $isCurrentMonth => 'Under Review',
                            $gpa < (float) $program->maintaining_grade => 'Rejected',
                            default => 'Active Scholar',
                        };

                        $applicationNo = sprintf('APP-%s-%d-%02d-%02d', $program->provider ?: 'SCH', $monthDate->year, $monthDate->month, $i + 1);
                        $application = ScholarshipApplication::query()->updateOrCreate(
                            ['application_no' => $applicationNo],
                            [
                                'scholarship_program_id' => $program->id,
                                'applicant_id' => $user->id,
                                'status' => $status,
                                'score' => min(100, (int) round($gpa)),
                                'progress' => $status === 'Active Scholar' ? 100 : 70,
                                'submitted_at' => $submittedDate,
                                'created_at' => $submittedDate,
                                'updated_at' => $submittedDate,
                            ]
                        );
                        $totalApps++;

                        if ($status === 'Active Scholar') {
                            $complianceRate = rand(30, 100);
                            $complianceStatus = $complianceRate >= 80 ? 'Compliant' : ($complianceRate >= 50 ? 'Pending Requirements' : 'Non-Compliant');

                            Scholar::query()->updateOrCreate(
                                [
                                    'scholarship_program_id' => $program->id,
                                    'user_id' => $user->id,
                                ],
                                [
                                    'scholarship_application_id' => $application->id,
                                    'scholar_id' => sprintf('SCH-%s-%04d', $program->provider ?: 'SCH', $user->id),
                                    'name' => $user->name,
                                    'program' => $program->name,
                                    'course' => $course,
                                    'year_level' => '3rd Year',
                                    'school' => 'University State College',
                                    'email' => $user->email,
                                    'gpa' => $gpa,
                                    'scholarship_status' => 'Active Scholar',
                                    'enrollment_status' => 'Enrolled',
                                    'compliance_rate' => $complianceRate,
                                    'compliance_status' => $complianceStatus,
                                    'date_approved' => $submittedDate->copy()->addDays(5),
                                    'created_at' => $submittedDate,
                                    'updated_at' => now(),
                                ]
                            );
                            $totalScholars++;
                        }
                    }
                }
            }
        });

        $this->info("Completed successfully! Seeded {$totalApps} historical applications and {$totalScholars} active scholars.");

        return self::SUCCESS;
    }
}
