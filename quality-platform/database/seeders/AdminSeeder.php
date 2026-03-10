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
            ['email' => 't-1358@ischooltech.com'],
            [
                'name' => 'Platform Admin',
                'password' => Hash::make('123456789'),
                'role' => 'admin',
                'project_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
