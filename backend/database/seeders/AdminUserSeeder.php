<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            [
                'email' => 'admin@clinic.local',
            ],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('Admin@12345'),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('Admin');
    }
}
