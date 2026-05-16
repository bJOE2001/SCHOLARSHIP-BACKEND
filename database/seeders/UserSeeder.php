<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        User::factory()->create([
            'name' => 'Student One',
            'email' => 'student1@example.com',
            'password' => 'password',
            'school_name' => 'ScholarSync State University',
            'family_income' => 15000,
        ]);

        User::factory()->create([
            'name' => 'Student Two',
            'email' => 'student2@example.com',
            'password' => 'password',
            'school_name' => 'ScholarSync State University',
            'family_income' => 30000,
        ]);

        User::factory()->create([
            'name' => 'Student Three',
            'email' => 'student3@example.com',
            'password' => 'password',
            'school_name' => 'ScholarSync State University',
            'family_income' => 50000,
        ]);
    }
}
