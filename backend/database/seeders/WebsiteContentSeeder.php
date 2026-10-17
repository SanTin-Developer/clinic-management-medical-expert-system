<?php

namespace Database\Seeders;

use App\Models\PublicPage;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;

/**
 * Seeds the singleton website settings row and the required system pages so the
 * public website renders with sensible defaults on a fresh install. Idempotent.
 */
class WebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $settings = WebsiteSetting::current();

        $settings->fill([
            'site_name' => $settings->site_name ?: 'RoyalCare',
            'tagline' => $settings->tagline ?: 'Compassionate care, expert medicine.',
            'hero_title' => $settings->hero_title ?: "Better Health\nBrighter Tomorrow",
            'hero_subtitle' => $settings->hero_subtitle ?: 'At RoyalCare Clinic, we provide compassionate, high-quality healthcare with modern facilities and expert medical professionals.',
            'about_summary' => $settings->about_summary ?: 'RoyalCare is a full-service medical clinic committed to providing accessible, high-quality healthcare to our community.',
            'seo_title' => $settings->seo_title ?: 'RoyalCare Medical Clinic',
            'seo_description' => $settings->seo_description ?: 'RoyalCare Medical Clinic — compassionate, expert healthcare for you and your family.',
        ])->save();

        $pages = [
            [
                'slug' => 'about',
                'title' => 'About Us',
                'excerpt' => 'Learn more about RoyalCare Medical Clinic.',
                'body' => '<p>RoyalCare is a full-service medical clinic dedicated to the health and well-being of our community. Our team of experienced doctors, nurses and specialists provides compassionate, patient-centred care.</p><p>Edit this page from the Website Management section of the admin dashboard.</p>',
            ],
            [
                'slug' => 'privacy-policy',
                'title' => 'Privacy Policy',
                'excerpt' => 'How we collect, use and protect your information.',
                'body' => '<p>We respect your privacy and are committed to protecting your personal information. This policy explains what data we collect through this website and how it is used.</p><p>Edit this page from the Website Management section of the admin dashboard.</p>',
            ],
            [
                'slug' => 'terms-of-use',
                'title' => 'Terms of Use',
                'excerpt' => 'The terms governing the use of this website.',
                'body' => '<p>By accessing this website you agree to these terms of use. The content provided is for general information only and does not constitute medical advice.</p><p>Edit this page from the Website Management section of the admin dashboard.</p>',
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
