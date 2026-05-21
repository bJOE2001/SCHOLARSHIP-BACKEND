<?php

namespace Database\Seeders;

use App\Models\ScholarshipNotification;
use App\Models\User;
use Illuminate\Database\Seeder;

class ScholarshipNotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $headOfficer = User::query()->where('email', 'head.officer@example.com')->firstOrFail();
        $studentOne = User::query()->where('email', 'student1@example.com')->firstOrFail();
        $studentTwo = User::query()->where('email', 'student2@example.com')->firstOrFail();

        ScholarshipNotification::create([
            'user_id' => $studentOne->id,
            'role' => 'student',
            'type' => 'status',
            'title' => 'Application Submitted',
            'message' => 'Your scholarship application has been submitted and is ready for review.',
            'notified_at' => now()->subDays(2),
            'payload' => ['applicationNo' => 'APP-2026-10001'],
        ]);

        ScholarshipNotification::create([
            'user_id' => $studentTwo->id,
            'role' => 'student',
            'type' => 'warning',
            'title' => 'Requirement Missing',
            'message' => 'One requirement is still missing from your scholarship application.',
            'notified_at' => now()->subDay(),
            'payload' => ['applicationNo' => 'APP-2026-10002'],
        ]);

        ScholarshipNotification::create([
            'user_id' => $headOfficer->id,
            'role' => 'officer',
            'type' => 'admin',
            'title' => 'Applications Need Review',
            'message' => 'There are scholarship applications waiting for review this week.',
            'notified_at' => now(),
            'payload' => ['count' => 3],
        ]);
    }
}
