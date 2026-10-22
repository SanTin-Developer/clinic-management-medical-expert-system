<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ContentBlock;
use App\Models\GalleryItem;
use App\Models\MediaAsset;
use App\Models\PublicPage;
use App\Models\Service;
use App\Models\WebsiteSetting;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class WebsitePublicTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_bootstrap_returns_settings_and_only_published_navigation(): void
    {
        WebsiteSetting::factory()->create();

        PublicPage::factory()->published()->create(['title' => 'About', 'slug' => 'about', 'show_in_footer' => true]);
        PublicPage::factory()->create(['title' => 'Secret', 'slug' => 'secret', 'show_in_footer' => true]);

        Service::factory()->published()->create(['title' => 'Cardiology']);
        Service::factory()->create(['title' => 'Hidden Service']);

        $this->getJson('/api/v1/website/bootstrap')
            ->assertOk()
            ->assertJsonPath('settings.site_name', 'RoyalCare')
            ->assertJsonCount(1, 'footer_pages')
            ->assertJsonPath('footer_pages.0.slug', 'about')
            ->assertJsonCount(1, 'services')
            ->assertJsonPath('services.0.title', 'Cardiology');
    }

    public function test_bootstrap_exposes_the_homepage_copy_from_content_blocks(): void
    {
        $asset = MediaAsset::factory()->create([
            'file_path' => 'https://example.com/hero.jpg',
            'disk' => 'cloudinary',
        ]);

        ContentBlock::create([
            'group' => 'home',
            'key' => 'home_hero_title',
            'body' => 'World-class care, close to home.',
        ]);

        ContentBlock::create([
            'group' => 'home',
            'key' => 'home_hero_image',
            'title' => 'Homepage hero image',
            'image_media_id' => $asset->id,
        ]);

        $blocks = collect($this->getJson('/api/v1/website/bootstrap')->assertOk()->json('content_blocks'))
            ->keyBy('key');

        $this->assertSame('World-class care, close to home.', $blocks['home_hero_title']['body']);
        $this->assertSame('https://example.com/hero.jpg', $blocks['home_hero_image']['image']['url']);
    }

    public function test_the_public_settings_payload_does_not_repeat_the_homepage_copy(): void
    {
        $settings = WebsiteSetting::factory()->create();

        // A database that still holds the legacy values must not publish them:
        // the homepage copy is served from the content blocks only.
        $settings->forceFill([
            'hero_title' => 'Legacy headline',
            'hero_subtitle' => 'Legacy subtitle',
            'about_summary' => 'Legacy blurb',
        ])->save();

        foreach (['/api/v1/website/bootstrap', '/api/v1/website/home'] as $endpoint) {
            $payload = $this->getJson($endpoint)->assertOk()->json('settings');

            foreach (['hero_title', 'hero_subtitle', 'about_summary', 'hero_media_id'] as $retired) {
                $this->assertArrayNotHasKey($retired, $payload);
            }
        }
    }

    public function test_public_services_only_lists_published_services(): void
    {
        Service::factory()->published()->create(['title' => 'Cardiology', 'sort_order' => 2]);
        Service::factory()->published()->create(['title' => 'Dentistry', 'sort_order' => 1]);
        Service::factory()->create(['title' => 'Draft Service', 'sort_order' => 0]);

        $this->getJson('/api/v1/website/services')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Dentistry')
            ->assertJsonPath('data.1.title', 'Cardiology')
            ->assertJsonMissing(['title' => 'Draft Service']);
    }

    public function test_public_pages_only_expose_published_content(): void
    {
        PublicPage::factory()->published()->create(['slug' => 'about']);
        PublicPage::factory()->create(['slug' => 'draft-page']);

        $this->getJson('/api/v1/website/pages/about')->assertOk()->assertJsonPath('data.slug', 'about');
        $this->getJson('/api/v1/website/pages/draft-page')->assertNotFound();

        $this->getJson('/api/v1/website/pages')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_public_article_show_increments_views_and_hides_drafts(): void
    {
        $article = Article::factory()->published()->create(['slug' => 'flu-season']);
        Article::factory()->create(['slug' => 'draft-article']);

        $this->getJson('/api/v1/website/articles/flu-season')
            ->assertOk()
            ->assertJsonPath('data.slug', 'flu-season');

        $this->assertSame(1, $article->fresh()->views);

        $this->getJson('/api/v1/website/articles/draft-article')->assertNotFound();
    }

    public function test_public_gallery_only_lists_published_images(): void
    {
        GalleryItem::factory()->published()->create(['title' => 'Lobby', 'category' => 'Facilities']);
        GalleryItem::factory()->create(['title' => 'Hidden']);

        $this->getJson('/api/v1/website/gallery')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Lobby')
            ->assertJsonPath('categories.0', 'Facilities');
    }

    public function test_media_route_redirects_external_assets(): void
    {
        $asset = MediaAsset::factory()->create(['file_path' => 'https://example.com/photo.jpg', 'disk' => 'cloudinary']);

        $this->get('/api/v1/website/media/'.$asset->id)
            ->assertRedirect('https://example.com/photo.jpg');
    }

    public function test_media_route_streams_local_assets(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('website/media/pic.jpg', 'binary');

        $asset = MediaAsset::factory()->create([
            'file_path' => 'website/media/pic.jpg',
            'disk' => 'public',
        ]);

        $this->get('/api/v1/website/media/'.$asset->id)->assertOk();
    }
}
