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
            KnowledgeBaseSeeder::class,
            ShiftTemplateSeeder::class,
            WebsiteContentSeeder::class,
            LegalDocumentSeeder::class,
            AboutContentBlockSeeder::class,
            WebsitePageContentSeeder::class,
        ]);

        // Demo logins are normally local/testing only. A hosted demo can opt in
        // with SEED_DEMO_USERS=true (set in render.yaml); turn it off before
        // handling real patient data.
        if (app()->environment(['local', 'testing']) || $this->demoUsersEnabled()) {
            $this->call(DemoUsersSeeder::class);
        }

        // The full demo dataset (including the 40-person staff directory and
        // the production-like page data) is local/testing by default. A hosted
        // demo can opt in with SEED_DEMO_DATA=true; never enable it on a real
        // clinic database.
        if (app()->environment(['local', 'testing']) || $this->demoDataEnabled()) {
            $this->call([
                KhmerDemoDataSeeder::class,
                StaffDirectorySeeder::class,
                QueueDemoSeeder::class,
                WardRoomBedDemoSeeder::class,
                InpatientDemoDataSeeder::class,
                ProductionLikeDemoSeeder::class,
                WebsiteDemoContentSeeder::class,
            ]);
        }
    }

    private function demoUsersEnabled(): bool
    {
        return filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOL);
    }

    private function demoDataEnabled(): bool
    {
        return filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL);
    }
}
