<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Session>
 */
class SessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'group_code' => fake()->bothify('G-###'),
            'session_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'slot' => fake()->randomElement(['08:00-09:30', '10:00-11:30', '14:00-15:30']),
            'recording_url' => fake()->optional()->url(),
        ];
    }
}
