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
        User::factory()->create([
            'name' => 'Student One',
            'email' => 'student1@example.com',
            'password' => 'password',
            'student_id' => 'STU-2026-0001',
            'birth_date' => '2004-04-18',
            'gender' => 'Female',
            'civil_status' => 'Single',
            'citizenship' => 'Filipino',
            'address' => 'Purok 2, Mabini Street',
            'barangay' => 'Poblacion',
            'city' => 'Bislig City',
            'province' => 'Surigao del Sur',
            'contact_number' => '09171234567',
            'campus' => 'Main Campus',
            'school_name' => 'ScholarSync State University',
            'course' => 'BS Information Technology',
            'year_level' => '3rd Year',
            'semester' => '1st Semester',
            'academic_year' => '2026-2027',
            'gpa' => 93.25,
            'family_income' => 120000,
            'enrollment_status' => 'Currently Enrolled',
            'academic_awards' => 'Dean\'s Lister',
            'father_name' => 'Juan Student',
            'mother_name' => 'Maria Student',
            'guardian_name' => 'Maria Student',
            'parent_occupation' => 'Fisher',
            'monthly_income' => 'Below PHP 20,000',
            'siblings' => 3,
            'studying_siblings' => 2,
            'income_bracket' => 'Below PHP 20,000',
        ]);

        User::factory()->create([
            'name' => 'Student Two',
            'email' => 'student2@example.com',
            'password' => 'password',
        ]);

        User::factory()->create([
            'name' => 'Student Three',
            'email' => 'student3@example.com',
            'password' => 'password',
        ]);

        User::factory()->headOfficer()->create([
            'name' => 'Head Scholarship Officer',
            'email' => 'head.officer@example.com',
            'password' => 'password',
        ]);

        User::factory()->officer()->create([
            'name' => 'TDP Scholarship Officer',
            'email' => 'tdp.officer@example.com',
            'password' => 'password',
        ]);
    }
}
