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
