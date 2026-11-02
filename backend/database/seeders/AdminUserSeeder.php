<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the first administrator account.
 *
 * On a fresh install the password is the documented bootstrap value. Re-running
 * the seeder in production must not reset a password the clinic has changed, so
 * the password is only (re)written in local/testing or when the account does not
 * exist yet.
 */
class AdminUserSeeder extends Seeder
{
    public const EMAIL = 'admin@clinic.local';

    public const BOOTSTRAP_PASSWORD = 'Admin@12345';

    public function run(): void
    {
        $admin = User::firstOrNew(['email' => self::EMAIL]);

        $admin->fill([
            'name' => 'System Administrator',
            'username' => 'admin',
            'email_verified_at' => now(),
            'account_status' => 'active',
        ]);

        if (! $admin->exists || app()->environment(['local', 'testing'])) {
            $admin->password = Hash::make(self::BOOTSTRAP_PASSWORD);
        }

        $admin->save();

        $admin->assignRole('Admin');
    }
}
