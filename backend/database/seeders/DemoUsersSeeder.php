<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $demoUsers = [
            ['name' => 'Demo Doctor', 'email' => 'doctor@clinic.local', 'role' => 'Doctor'],
            ['name' => 'Demo Nurse', 'email' => 'nurse@clinic.local', 'role' => 'Nurse'],
            ['name' => 'Demo Receptionist', 'email' => 'receptionist@clinic.local', 'role' => 'Receptionist'],
            ['name' => 'Demo Patient', 'email' => 'patient@clinic.local', 'role' => 'Patient'],
        ];

        foreach ($demoUsers as $definition) {
            $user = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => Hash::make('Password@12345'),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$definition['role']]);
        }
    }
}
