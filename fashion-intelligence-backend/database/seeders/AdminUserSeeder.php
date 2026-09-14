<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@fashion-intelligence.local')],
            [
                'name'              => 'Admin',
                'email'             => env('ADMIN_EMAIL', 'admin@fashion-intelligence.local'),
                'password'          => Hash::make(env('ADMIN_PASSWORD', 'changeme123')),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created: ' . env('ADMIN_EMAIL', 'admin@fashion-intelligence.local'));
        $this->command->warn('Remember to set ADMIN_EMAIL and ADMIN_PASSWORD in your .env');
    }
}
