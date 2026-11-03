<?php

namespace Database\Seeders;

use App\Models\PublicPage;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the singleton website settings row and the About system page so the
 * public website renders with sensible defaults on a fresh install. Idempotent.
 *
 * Only site-wide settings live here. The homepage copy is seeded by
 * WebsitePageContentSeeder, which owns it as content blocks, and the two legal
 * documents are owned by LegalDocumentSeeder (they need structured
 * `policy_content`, not the plain `body` this seeder writes).
 */
class WebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $settings = WebsiteSetting::current();

        $settings->fill([
            'site_name' => $settings->site_name ?: 'RoyalCare',
            'tagline' => $settings->tagline ?: 'Compassionate care, expert medicine.',
            'seo_title' => $settings->seo_title ?: 'RoyalCare Medical Clinic',
            'seo_description' => $settings->seo_description ?: 'RoyalCare Medical Clinic — compassionate, expert healthcare for you and your family.',
            'business_hours' => $settings->business_hours ?: 'Every day: Open 24 hours',
        ])->save();

        $pages = [
            [
                'slug' => 'about',
                'title' => 'About Us',
                'excerpt' => 'Learn more about RoyalCare Medical Clinic.',
                'body' => '<p>RoyalCare is a full-service medical clinic dedicated to the health and well-being of our community. Our team of experienced doctors, nurses and specialists provides compassionate, patient-centred care.</p><p>Edit this page from the Website Management section of the admin dashboard.</p>',
            ],
        ];

        foreach ($pages as $page) {
            PublicPage::firstOrCreate(
                ['slug' => $page['slug']],
                [
                    'title' => $page['title'],
                    'excerpt' => $page['excerpt'],
                    'body' => $page['body'],
                    'status' => PublicPage::STATUS_PUBLISHED,
                    'is_system' => true,
                    'show_in_footer' => true,
                    'published_at' => now(),
                ],
            );
        }
    }
}
