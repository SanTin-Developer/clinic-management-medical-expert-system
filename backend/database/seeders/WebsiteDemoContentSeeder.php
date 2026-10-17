<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\PublicTeamProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds illustrative demo content for the public website so the homepage
 * Services, Team, News and Gallery sections render on a fresh local install.
 *
 * This is sample content for local/testing only — replace it from the Website
 * Management area of the admin dashboard. Idempotent: existing records are kept.
 */
class WebsiteDemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedServices();
        $this->seedTeam();
        $this->seedArticles();
        $this->seedGallery();
    }

    /**
     * Illustrative services promoted on the homepage and header navigation.
     */
    private function seedServices(): void
    {
        $images = $this->photoAssetIds();

        $services = [
            [
                'title' => 'General Consultation',
                'icon' => 'Stethoscope',
                'short_description' => 'Comprehensive check-ups and primary care for everyday health concerns.',
            ],
            [
                'title' => 'Cardiology',
                'icon' => 'HeartPulse',
                'short_description' => 'Heart screening, diagnosis and ongoing cardiovascular care.',
            ],
            [
                'title' => 'Pediatrics',
                'icon' => 'Baby',
                'short_description' => 'Gentle, attentive healthcare for infants, children and adolescents.',
            ],
            [
                'title' => 'Laboratory & Diagnostics',
                'icon' => 'Microscope',
                'short_description' => 'On-site blood tests, imaging referrals and same-day results.',
            ],
            [
                'title' => 'Vaccination & Immunization',
                'icon' => 'Syringe',
                'short_description' => 'Routine and travel vaccines for children and adults.',
            ],
            [
                'title' => 'Emergency Care',
                'icon' => 'Ambulance',
                'short_description' => 'Urgent assessment and stabilisation during clinic hours.',
            ],
        ];

        foreach ($services as $index => $service) {
            Service::firstOrCreate(
                ['slug' => Str::slug($service['title'])],
                [
                    'title' => $service['title'],
                    'icon' => $service['icon'],
                    'short_description' => $service['short_description'],
                    'description' => $service['short_description'].' Our team of specialists delivers this care in a modern, patient-first environment at RoyalCare Clinic.',
                    'image_media_id' => $images[$index % max(count($images), 1)] ?? null,
                    'is_featured' => true,
                    'is_published' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * Public team profiles shown in the "Our Team" section.
     */
    private function seedTeam(): void
    {
        $team = [
            ['Dr. Sokha Chan', 'Cardiologist', 'Interventional Cardiology'],
            ['Dr. Dara Kim', 'General Practitioner', 'Family Medicine'],
            ['Dr. Vanna Sok', 'Pediatrician', 'Child & Adolescent Health'],
            ['Dr. Rithy Meas', 'Internal Medicine', 'Chronic Disease Management'],
            ['Dr. Chanthou Ly', 'Obstetrician & Gynecologist', "Women's Health"],
            ['Sreypov Heng', 'Head Nurse', 'Patient Care & Triage'],
        ];

        foreach ($team as $index => [$name, $position, $specialization]) {
            PublicTeamProfile::firstOrCreate(
                ['name' => $name],
                [
                    'position' => $position,
                    'specialization' => $specialization,
                    'bio' => $name.' is part of the RoyalCare clinical team, dedicated to compassionate, patient-first care.',
                    'is_featured' => true,
                    'is_published' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * News & Health articles shown on the homepage and News & Health page.
     */
    private function seedArticles(): void
    {
        $images = $this->photoAssetIds();
        $authorId = User::query()->where('email', 'admin@clinic.local')->value('id');

        $articles = [
            [
                'title' => '5 Everyday Habits for a Healthier Heart',
                'category' => 'Health Tips',
                'excerpt' => 'Small, consistent choices can make a real difference to your cardiovascular health over time.',
            ],
            [
                'title' => 'When Should You See a Doctor About a Fever?',
                'category' => 'Prevention',
                'excerpt' => 'A guide to knowing when a fever can be managed at home and when to seek medical advice.',
            ],
            [
                'title' => 'RoyalCare Opens a New Diagnostic Laboratory',
                'category' => 'Clinic News',
                'excerpt' => 'Our new on-site laboratory brings faster testing and same-day results for patients.',
            ],
        ];

        foreach ($articles as $index => $article) {
            Article::firstOrCreate(
                ['slug' => Str::slug($article['title'])],
                [
                    'title' => $article['title'],
                    'excerpt' => $article['excerpt'],
                    'body' => '<p>'.$article['excerpt'].'</p><p>This is sample article content for the RoyalCare website. Edit or replace it from the Website Management area of the admin dashboard.</p>',
                    'cover_media_id' => $images[$index % max(count($images), 1)] ?? null,
                    'category' => $article['category'],
                    'tags' => [$article['category']],
                    'status' => Article::STATUS_PUBLISHED,
                    'is_featured' => true,
                    'published_at' => now()->subDays($index),
                    'author_id' => $authorId,
                ],
            );
        }
    }

    /**
     * Facility and event images shown in the "A Glimpse Inside" gallery.
     */
    private function seedGallery(): void
    {
        $images = $this->photoAssetIds();

        $items = [
            ['Modern reception & waiting area', 'Facilities'],
            ['Private consultation room', 'Facilities'],
            ['On-site diagnostic laboratory', 'Equipment'],
            ['Cardiology & ECG suite', 'Equipment'],
            ['Comfortable patient rooms', 'Facilities'],
            ['Community health day', 'Events'],
        ];

        foreach ($items as $index => [$title, $category]) {
            GalleryItem::firstOrCreate(
                ['title' => $title],
                [
                    'caption' => $title.' at RoyalCare Clinic.',
                    'image_media_id' => $images[$index % max(count($images), 1)] ?? null,
                    'category' => $category,
                    'is_published' => true,
                    'sort_order' => $index,
                ],
            );
        }
    }

    /**
     * IDs of existing photo assets to reuse for demo imagery, newest-agnostic.
     *
     * @return list<int>
     */
    private function photoAssetIds(): array
    {
        return MediaAsset::query()
            ->where('mime_type', 'like', 'image/jpeg%')
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
