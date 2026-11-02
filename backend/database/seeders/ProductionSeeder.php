<?php

namespace Database\Seeders;

use App\Models\PublicPage;
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
        ]);

        // Public website baseline. The About page is intentionally NOT seeded:
        // it is authored by the clinic in the admin CMS. Only the two legal
        // documents the public routes resolve are created, as published system
        // pages, so /privacy-policy and /terms-of-use resolve on a fresh
        // deployment instead of returning a 404.
        $this->seedWebsiteBaseline();
    }

    private function seedWebsiteBaseline(): void
    {
        $settings = WebsiteSetting::current();

        $settings->fill([
            'site_name' => $settings->site_name ?: 'RoyalCare',
            'tagline' => $settings->tagline ?: 'Compassionate care, expert medicine.',
            'seo_title' => $settings->seo_title ?: 'RoyalCare Medical Clinic',
            'seo_description' => $settings->seo_description ?: 'RoyalCare Medical Clinic — compassionate, expert healthcare for you and your family.',
            'business_hours' => $settings->business_hours ?: 'Every day: Open 24 hours',
        ])->save();

        foreach (LegalDocumentContent::pages() as $page) {
            // Search on slug only, and only fill when creating: re-running must
            // never overwrite content the clinic edited in the admin.
            if (PublicPage::query()->where('slug', $page['slug'])->exists()) {
                continue;
            }

            PublicPage::create([
                'slug' => $page['slug'],
                'title' => $page['title'],
                'title_km' => $page['title_km'],
                'excerpt' => $page['excerpt'],
                'excerpt_km' => $page['excerpt_km'],
                'policy_content' => $page['policy_content'],
                'internal_notes' => $page['internal_notes'],
                'status' => PublicPage::STATUS_PUBLISHED,
                'is_system' => true,
                'show_in_footer' => true,
                'published_at' => now(),
            ]);
        }
    }
}
