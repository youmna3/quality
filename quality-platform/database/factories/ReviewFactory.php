<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Tutor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tutor_id' => Tutor::factory(),
            'reviewer_name' => fake()->name(),
            'project_id' => Project::factory(),
            'session_id' => null,
            'tutor_role' => fake()->randomElement(['main', 'cover']),
            'session_date' => fake()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'slot' => fake()->randomElement(['08:00-09:30', '10:00-11:30', '14:00-15:30']),
            'group_code' => fake()->bothify('G-###'),
            'recorded_link' => fake()->url(),
            'issue_text' => fake()->paragraph(),
            'positive_note' => fake()->optional()->sentence(),
            'negative_note' => fake()->optional()->sentence(),
            'total_score' => fake()->optional()->randomFloat(2, 50, 100),
            'submitted_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
