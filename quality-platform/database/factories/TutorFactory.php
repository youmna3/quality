<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tutor>
 */
class TutorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tutor_code' => fake()->unique()->bothify('TUT###??'),
            'name_en' => fake()->name(),
            'project_id' => Project::factory(),
            'mentor_name' => fake()->optional()->name(),
            'shift' => fake()->optional()->randomElement(['Morning', 'Evening', 'Night']),
            'is_active' => fake()->boolean(90),
            'user_id' => null,
        ];
    }
}
