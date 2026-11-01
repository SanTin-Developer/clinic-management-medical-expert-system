<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo accounts for local/testing logins — one per role.
 *
 * Every account shares the password below. The Admin account is created by
 * AdminUserSeeder (admin@clinic.local / Admin@12345) so it exists in every
 * environment; this seeder only runs in local/testing.
 *
 * Login accepts either the email or the username (see AuthController::login).
 */
class DemoUsersSeeder extends Seeder
{
    public const PASSWORD = 'Password@12345';

    public function run(): void
    {
        $demoUsers = [
            ['name' => 'Demo Doctor', 'username' => 'doctor', 'email' => 'doctor@clinic.local', 'role' => 'Doctor'],
            ['name' => 'Demo Nurse', 'username' => 'nurse', 'email' => 'nurse@clinic.local', 'role' => 'Nurse'],
            ['name' => 'Demo Head Nurse', 'username' => 'headnurse', 'email' => 'headnurse@clinic.local', 'role' => 'Head Nurse'],
            ['name' => 'Demo Receptionist', 'username' => 'receptionist', 'email' => 'receptionist@clinic.local', 'role' => 'Receptionist'],
            ['name' => 'Demo Pharmacist', 'username' => 'pharmacist', 'email' => 'pharmacist@clinic.local', 'role' => 'Pharmacist'],
            ['name' => 'Demo Patient', 'username' => 'patient', 'email' => 'patient@clinic.local', 'role' => 'Patient'],
        ];

        foreach ($demoUsers as $definition) {
            $user = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'username' => $definition['username'],
                    'password' => Hash::make(self::PASSWORD),
                    'email_verified_at' => now(),
                    'activated_at' => now(),
                    // Re-seeding must always leave the demo logins usable, even
                    // if someone suspended or locked the account while testing.
                    'account_status' => 'active',
                    'suspended_at' => null,
                    'suspended_reason' => null,
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]
            );

            $user->syncRoles([$definition['role']]);
        }
    }
}
