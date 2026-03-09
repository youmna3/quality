<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@quality.local'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'project_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
