<?php

namespace Database\Seeders;

use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

/**
 * Reference data every deployment needs before it can serve requests.
 *
 * Safe to run on every container start: each step is idempotent and leaves
 * admin-edited records untouched. Demo content lives in DatabaseSeeder instead.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            KnowledgeBaseSeeder::class,
            LegalDocumentSeeder::class,
        ]);

        // Public website baseline. The About page is intentionally NOT seeded:
        // it is authored by the clinic in the admin CMS. LegalDocumentSeeder
        // above only touches the two legal documents the public routes resolve,
        // so /privacy-policy and /terms-of-use never 404 on a fresh deployment.
        $this->seedWebsiteSettings();
    }

    private function seedWebsiteSettings(): void
    {
        $settings = WebsiteSetting::current();

        $settings->fill([
            'site_name' => $settings->site_name ?: 'RoyalCare',
            'tagline' => $settings->tagline ?: 'Compassionate care, expert medicine.',
            'seo_title' => $settings->seo_title ?: 'RoyalCare Medical Clinic',
            'seo_description' => $settings->seo_description ?: 'RoyalCare Medical Clinic — compassionate, expert healthcare for you and your family.',
            'business_hours' => $settings->business_hours ?: 'Every day: Open 24 hours',
        ])->save();
    }
}
