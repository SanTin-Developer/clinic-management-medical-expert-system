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

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                KhmerDemoDataSeeder::class,
                QueueDemoSeeder::class,
                WardRoomBedDemoSeeder::class,
                InpatientDemoDataSeeder::class,
                WebsiteDemoContentSeeder::class,
            ]);
        }
    }

    private function demoUsersEnabled(): bool
    {
        return filter_var(env('SEED_DEMO_USERS', false), FILTER_VALIDATE_BOOL);
    }
}
