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
        $this->seedUser([
            'name' => 'Head Scholarship Officer',
            'email' => 'head.officer@example.com',
            'password' => 'password',
            'role' => 'head_officer',
            'status' => 'Active',
            'department' => 'Scholarship Programs Office',
            'student_id' => null,
            'school_name' => null,
        ]);

        $this->seedUser([
            'name' => 'TDP Scholarship Officer',
            'email' => 'tdp.officer@example.com',
            'password' => 'password',
            'role' => 'officer',
            'status' => 'Active',
            'department' => 'Scholarship Programs Office',
            'student_id' => null,
            'school_name' => null,
        ]);

        $this->seedUser([
            'name' => 'Merit Scholarship Officer',
            'email' => 'merit.officer@example.com',
            'password' => 'password',
            'role' => 'officer',
            'status' => 'Active',
            'department' => 'Scholarship Programs Office',
            'student_id' => null,
            'school_name' => null,
        ]);
    }

    /**
     * Create or refresh one seeded user by email.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function seedUser(array $attributes): void
    {
        User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            $attributes,
        );
    }
}
