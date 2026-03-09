<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tutor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Complaint>
 */
class ComplaintFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tutor_id' => Tutor::factory(),
            'project_id' => Project::factory(),
            'session_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'slot' => fake()->randomElement(['08:00-09:30', '10:00-11:30', '14:00-15:30']),
            'group_code' => fake()->bothify('G-###'),
            'complaint_text' => fake()->paragraph(),
            'status' => fake()->randomElement(['new', 'triaged', 'linked', 'resolved']),
            'created_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
