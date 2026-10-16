<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            ShiftTemplateSeeder::class,
            WebsiteContentSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                DemoUsersSeeder::class,
                KhmerDemoDataSeeder::class,
                WardRoomBedDemoSeeder::class,
                InpatientDemoDataSeeder::class,
            ]);
        }
    }
}
