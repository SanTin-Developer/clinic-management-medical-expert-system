<?php

namespace Tests\Feature;

use App\Models\PublicPage;
use Database\Seeders\LegalDocumentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalDocumentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_both_legal_documents_with_structured_content(): void
    {
        $this->seed(LegalDocumentSeeder::class);

        foreach (['privacy-policy', 'terms-of-use'] as $slug) {
            $page = PublicPage::query()->where('slug', $slug)->firstOrFail();

            $this->assertTrue($page->is_system);
            $this->assertTrue($page->show_in_footer);
            $this->assertSame(PublicPage::STATUS_PUBLISHED, $page->status);
            $this->assertNotEmpty($page->policy_content['en']['sections']);
            $this->assertNotEmpty($page->policy_content['km']['sections']);
        }

        $this->getJson('/api/v1/website/pages/privacy-policy')
            ->assertOk()
            ->assertJsonPath('data.slug', 'privacy-policy')
            ->assertJsonCount(14, 'data.policy_content.en.sections');
    }

    public function test_it_backfills_a_legal_row_created_before_the_baseline(): void
    {
        $page = PublicPage::factory()->published()->create([
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'body' => 'Use Edit document to manage bilingual sections and preview draft changes.',
            'is_system' => false,
        ]);

        $this->seed(LegalDocumentSeeder::class);

        $page->refresh();

        $this->assertNotEmpty($page->policy_content['en']['sections']);
        $this->assertNotEmpty($page->policy_content['km']['sections']);
        $this->assertTrue($page->is_system);
        $this->assertSame('Use Edit document to manage bilingual sections and preview draft changes.', $page->body);
    }

    public function test_it_never_overwrites_content_saved_from_the_admin(): void
    {
        $page = PublicPage::factory()->published()->create(['slug' => 'terms-of-use']);

        $saved = [
            'en' => [
                'title' => 'Clinic Terms',
                'introduction' => 'Written by the clinic.',
                'sections' => [
                    ['order' => 1, 'visible' => true, 'heading' => 'Custom heading', 'body' => 'Custom body'],
                ],
            ],
        ];

        $page->update(['policy_content' => $saved]);

        $this->seed(LegalDocumentSeeder::class);

        $this->assertSame($saved, $page->fresh()->policy_content);
    }
}
