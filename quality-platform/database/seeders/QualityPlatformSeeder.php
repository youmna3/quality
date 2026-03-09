<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\Flag;
use App\Models\Project;
use App\Models\Review;
use App\Models\Session;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class QualityPlatformSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            Project::updateOrCreate(
                ['code' => 'DEMI'],
                ['name' => 'Digital English Mastery Initiative']
            ),
            Project::updateOrCreate(
                ['code' => 'DECI'],
                ['name' => 'Digital English Coaching Initiative']
            ),
        ];

        foreach ($projects as $project) {
            $sessions = Session::factory()
                ->count(6)
                ->create(['project_id' => $project->id]);

            for ($i = 1; $i <= 5; $i++) {
                $code = sprintf('%s-T%02d', $project->code, $i);
                $name = sprintf('%s Tutor %02d', $project->code, $i);

                $user = User::updateOrCreate(
                    ['email' => strtolower($code).'@quality.local'],
                    [
                        'name' => $name,
                        'password' => Hash::make('password123'),
                        'role' => 'tutor',
                        'project_id' => $project->id,
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]
                );

                $tutor = Tutor::updateOrCreate(
                    ['tutor_code' => $code],
                    [
                        'name_en' => $name,
                        'project_id' => $project->id,
                        'mentor_name' => fake()->name(),
                        'shift' => fake()->randomElement(['Morning', 'Evening']),
                        'is_active' => true,
                        'user_id' => $user->id,
                    ]
                );

                $reviews = Review::factory()
                    ->count(4)
                    ->create([
                        'tutor_id' => $tutor->id,
                        'project_id' => $project->id,
                        'session_id' => $sessions->random()->id,
                        'reviewer_name' => fake()->name(),
                    ]);

                foreach ($reviews as $review) {
                    Flag::factory()
                        ->count(fake()->numberBetween(0, 2))
                        ->create([
                            'review_id' => $review->id,
                            'tutor_id' => $tutor->id,
                            'status' => fake()->randomElement(['open', 'resolved', 'accepted', 'partial']),
                        ]);
                }

                Complaint::factory()
                    ->count(2)
                    ->create([
                        'tutor_id' => $tutor->id,
                        'project_id' => $project->id,
                        'status' => fake()->randomElement(['new', 'triaged', 'linked', 'resolved']),
                    ]);
            }
        }
    }
}
