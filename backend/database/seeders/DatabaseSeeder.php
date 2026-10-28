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
            AboutContentBlockSeeder::class,
            WebsitePageContentSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                DemoUsersSeeder::class,
                KhmerDemoDataSeeder::class,
                QueueDemoSeeder::class,
                WardRoomBedDemoSeeder::class,
                InpatientDemoDataSeeder::class,
                WebsiteDemoContentSeeder::class,
            ]);
        }
    }
}
