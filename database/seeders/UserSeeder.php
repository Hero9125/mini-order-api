<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@miniorderapi.com'],
            [
                'name'     => 'Admin User',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ]
        );

        // Regular user
        User::firstOrCreate(
            ['email' => 'user@miniorderapi.com'],
            [
                'name'     => 'Regular User',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ]
        );

        // Additional random users
        User::factory()->count(5)->create();
    }
}
