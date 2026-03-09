<?php

namespace Database\Factories;

use App\Models\Review;
use App\Models\Tutor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Flag>
 */
class FlagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'tutor_id' => Tutor::factory(),
            'color' => fake()->randomElement(['yellow', 'red']),
            'subcategory' => fake()->randomElement(['Attendance', 'Delivery', 'Process']),
            'reason' => fake()->sentence(),
            'duration_text' => fake()->optional()->randomElement(['00:02:30', '00:05:00', '00:01:15']),
            'status' => fake()->randomElement(['open', 'accepted', 'removed', 'partial', 'appealed', 'resolved']),
            'decided_by' => null,
            'decided_at' => null,
            'created_at' => fake()->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
