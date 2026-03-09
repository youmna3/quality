<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->randomElement(['DEMI', 'DECI']),
            'name' => fake()->randomElement(['Digital English Mastery Initiative', 'Digital English Coaching Initiative']),
        ];
    }
}
