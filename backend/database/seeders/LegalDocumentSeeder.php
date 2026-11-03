<?php

namespace Database\Seeders;

use App\Models\PublicPage;
use Illuminate\Database\Seeder;

/**
 * Baseline for the two legal documents the public routes resolve
 * (/privacy-policy and /terms-of-use).
 *
 * Idempotent in two separate ways:
 *
 * - A missing row is created as a published system page with the reviewed
 *   bilingual copy from LegalDocumentContent.
 * - A row that already exists but has no structured `policy_content` (created
 *   by an older WebsiteContentSeeder or by hand in the admin page form) is
 *   backfilled, so it stops rendering as an empty document.
 *
 * A row that already has `policy_content` is never touched: once the clinic
 * saves the document from the admin, it owns the copy.
 */
class LegalDocumentSeeder extends Seeder
{
    public function run(): void
    {
        foreach (LegalDocumentContent::pages() as $page) {
            $existing = PublicPage::query()->where('slug', $page['slug'])->first();

            if ($existing === null) {
                $this->createPage($page);

                continue;
            }

            if ($this->hasStructuredContent($existing)) {
                continue;
            }

            $existing->fill([
                'title_km' => $existing->title_km ?: $page['title_km'],
                'excerpt' => $existing->excerpt ?: $page['excerpt'],
                'excerpt_km' => $existing->excerpt_km ?: $page['excerpt_km'],
                'policy_content' => $page['policy_content'],
                'internal_notes' => $existing->internal_notes ?: $page['internal_notes'],
                'is_system' => true,
                'show_in_footer' => true,
            ])->save();
        }
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function createPage(array $page): void
    {
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

    /**
     * Any saved language with at least one section means the clinic has edited
     * the document, so the baseline must leave the whole row alone.
     */
    private function hasStructuredContent(PublicPage $page): bool
    {
        $content = $page->policy_content;

        if (! is_array($content)) {
            return false;
        }

        foreach (['en', 'km'] as $language) {
            if (! empty($content[$language]['sections'])) {
                return true;
            }
        }

        return false;
    }
}
