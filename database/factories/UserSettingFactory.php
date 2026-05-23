<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSetting>
 */
class UserSettingFactory extends Factory
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
            'settings' => [
                'emailAlerts' => true,
                'riskAlerts' => true,
                'defaultRange' => 'Last 6 months',
                'tableDensity' => 'Comfortable',
            ],
        ];
    }
}
