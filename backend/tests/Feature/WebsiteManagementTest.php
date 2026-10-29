<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\MediaAsset;
use App\Models\PublicPage;
use App\Models\Service;
use App\Models\WebsiteSetting;
use App\Services\Website\WebsiteMediaStorage;
use Database\Seeders\AboutContentBlockSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\WebsitePageContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class WebsiteManagementTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($name, $png);
    }

    private function useCloudinary(): void
    {
        config([
            'documents.poster_disk' => 'cloudinary',
            'services.cloudinary' => [
                'cloud_name' => 'demo',
                'api_key' => 'key',
                'api_secret' => 'secret',
                'folder' => 'Clinic Management System and Medical Expert systems',
                'secure' => true,
            ],
        ]);

        app()->forgetInstance(WebsiteMediaStorage::class);
    }

    private function cloudinaryAsset(string $name = 'shared'): MediaAsset
    {
        return MediaAsset::factory()->create([
            'name' => $name.'.jpg',
            'file_path' => 'https://res.cloudinary.com/demo/image/upload/v1/clinic/website/media/'.$name.'.jpg',
            'public_id' => 'clinic/website/media/'.$name,
            'resource_type' => 'image',
            'disk' => 'cloudinary',
        ]);
    }

    public function test_admin_can_view_website_dashboard(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        PublicPage::factory()->published()->create();
        Service::factory()->published()->create();

        $this->getJson('/api/v1/website/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.pages', 1)
            ->assertJsonPath('stats.services_published', 1);
    }

    public function test_non_admin_cannot_access_website_management(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        $this->getJson('/api/v1/website/admin/dashboard')->assertForbidden();
        $this->getJson('/api/v1/website/admin/pages')->assertForbidden();
        $this->postJson('/api/v1/website/admin/pages', ['title' => 'Nope'])->assertForbidden();
        $this->getJson('/api/v1/website/admin/media')->assertForbidden();
    }

    public function test_admin_can_create_publish_and_list_pages(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $id = $this->postJson('/api/v1/website/admin/pages', [
            'title' => 'Our History',
            'body' => '<p>Founded in 2001.</p>',
            'status' => 'published',
        ])->assertCreated()
            ->assertJsonPath('data.slug', 'our-history')
            ->json('data.id');

        $this->getJson('/api/v1/website/admin/pages')
            ->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.published', 1);

        $this->postJson("/api/v1/website/admin/pages/{$id}/unpublish")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/v1/website/admin/pages/{$id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_system_pages_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $page = PublicPage::factory()->published()->create(['is_system' => true]);

        $this->deleteJson('/api/v1/website/admin/pages/'.$page->id)->assertStatus(422);
        $this->assertDatabaseHas('public_pages', ['id' => $page->id]);
    }

    public function test_admin_can_manage_services_and_articles(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $serviceId = $this->postJson('/api/v1/website/admin/services', [
            'title' => 'Pediatrics',
            'short_description' => 'Care for children.',
            'is_published' => true,
        ])->assertCreated()->json('data.id');

        $this->putJson('/api/v1/website/admin/services/'.$serviceId, ['title' => 'Pediatrics & Child Health'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Pediatrics & Child Health');

        $articleId = $this->postJson('/api/v1/website/admin/articles', [
            'title' => 'Staying Hydrated',
            'body' => '<p>Drink water.</p>',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/website/admin/articles/{$articleId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->deleteJson('/api/v1/website/admin/services/'.$serviceId)->assertOk();
        $this->assertDatabaseCount('services', 0);
    }

    public function test_gallery_requires_an_image_on_create(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->postJson('/api/v1/website/admin/gallery', ['title' => 'No image'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_admin_can_upload_and_delete_media(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));
        Storage::fake('public');
        app()->forgetInstance(WebsiteMediaStorage::class);

        $id = $this->post('/api/v1/website/admin/media', [
            'file' => $this->fakePng('lobby.png'),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $this->assertDatabaseCount('media_assets', 1);

        $this->deleteJson('/api/v1/website/admin/media/'.$id)->assertOk();
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_media_library_lists_usage_and_blocks_deleting_a_shared_asset(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudinary.com/v1_1/demo/image/destroy' => Http::response(['result' => 'ok'], 200),
        ]);

        $asset = $this->cloudinaryAsset('shared');
        $service = Service::factory()->create(['image_media_id' => $asset->id]);

        $this->getJson('/api/v1/website/admin/media')
            ->assertOk()
            ->assertJsonPath('data.0.usage_count', 1)
            ->assertJsonPath('data.0.usage_references.0.type', 'service')
            ->assertJsonPath('data.0.usage_references.0.id', $service->id);

        $this->deleteJson('/api/v1/website/admin/media/'.$asset->id)
            ->assertStatus(409)
            ->assertJsonPath('references.0.type', 'service');

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
        Http::assertNothingSent();
    }

    public function test_non_admin_cannot_permanently_delete_media(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        $asset = MediaAsset::factory()->create();

        $this->deleteJson('/api/v1/website/admin/media/'.$asset->id)->assertForbidden();

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_unused_cloudinary_asset_is_permanently_deleted(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));
        $this->useCloudinary();
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudinary.com/v1_1/demo/image/destroy' => Http::response(['result' => 'ok'], 200),
        ]);

        $asset = $this->cloudinaryAsset('unused');

        $this->deleteJson('/api/v1/website/admin/media/'.$asset->id)->assertOk();

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/image/destroy')
            && $request['public_id'] === 'clinic/website/media/unused');
    }

    public function test_cloudinary_failure_keeps_the_media_record_for_retry(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));
        $this->useCloudinary();
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudinary.com/v1_1/demo/image/destroy' => Http::response(['error' => ['message' => 'unavailable']], 503),
        ]);

        $asset = $this->cloudinaryAsset('retry');

        $this->deleteJson('/api/v1/website/admin/media/'.$asset->id)
            ->assertStatus(502)
            ->assertJsonPath(
                'message',
                'Cloudinary could not delete this image. The media record was kept so you can retry safely.'
            );

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_removing_an_image_from_a_saved_form_cleans_up_the_unused_asset(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));
        $this->useCloudinary();
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudinary.com/v1_1/demo/image/destroy' => Http::response(['result' => 'ok'], 200),
        ]);

        $asset = $this->cloudinaryAsset('old-service');
        $service = Service::factory()->create(['image_media_id' => $asset->id]);

        $this->putJson('/api/v1/website/admin/services/'.$service->id, [
            'remove_image' => true,
        ])->assertOk()->assertJsonPath('media_cleanup.status', 'deleted');

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/image/destroy')
            && $request['public_id'] === 'clinic/website/media/old-service');
    }

    public function test_replacing_an_image_deletes_only_the_old_unused_asset(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));
        $this->useCloudinary();
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/image/upload')) {
                return Http::response([
                    'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/clinic/website/media/new.jpg',
                    'public_id' => 'clinic/website/media/new',
                    'resource_type' => 'image',
                ], 200);
            }

            return Http::response(['result' => 'ok'], 200);
        });

        $old = $this->cloudinaryAsset('old-replaced');
        $service = Service::factory()->create(['image_media_id' => $old->id]);

        $this->put('/api/v1/website/admin/services/'.$service->id, [
            'image' => $this->fakePng('new.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.image.public_id', 'clinic/website/media/new');

        $this->assertDatabaseMissing('media_assets', ['id' => $old->id]);
        $this->assertDatabaseHas('media_assets', [
            'public_id' => 'clinic/website/media/new',
            'resource_type' => 'image',
        ]);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/image/destroy')
            && $request['public_id'] === 'clinic/website/media/old-replaced');
    }

    public function test_deleting_a_record_cleans_up_its_unused_image(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));
        $this->useCloudinary();
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudinary.com/v1_1/demo/image/destroy' => Http::response(['result' => 'ok'], 200),
        ]);

        $asset = $this->cloudinaryAsset('record-delete');
        $service = Service::factory()->create(['image_media_id' => $asset->id]);

        $this->deleteJson('/api/v1/website/admin/services/'.$service->id)
            ->assertOk()
            ->assertJsonPath('media_cleanup.status', 'deleted');

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
    }

    public function test_deleting_a_record_does_not_delete_an_image_shared_by_another_record(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));
        $this->useCloudinary();
        Http::preventStrayRequests();
        Http::fake([
            'api.cloudinary.com/v1_1/demo/image/destroy' => Http::response(['result' => 'ok'], 200),
        ]);

        $asset = $this->cloudinaryAsset('shared-record-delete');
        $service = Service::factory()->create(['image_media_id' => $asset->id]);
        Service::factory()->create(['image_media_id' => $asset->id]);

        $this->deleteJson('/api/v1/website/admin/services/'.$service->id)
            ->assertOk()
            ->assertJsonPath('media_cleanup.status', 'in_use');

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
        Http::assertNothingSent();
    }

    public function test_admin_can_update_settings(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->getJson('/api/v1/website/admin/settings')->assertOk();

        $this->putJson('/api/v1/website/admin/settings', [
            'site_name' => 'RoyalCare Medical Center',
            'primary_phone' => '+855 12 345 678',
            'email' => 'hello@royalcare.test',
            'facebook_url' => 'https://facebook.com/royalcare',
        ])->assertOk()
            ->assertJsonPath('data.site_name', 'RoyalCare Medical Center')
            ->assertJsonPath('data.facebook_url', 'https://facebook.com/royalcare');

        $this->assertSame('RoyalCare Medical Center', WebsiteSetting::current()->site_name);
    }

    public function test_website_settings_no_longer_own_the_homepage_copy(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->seed(WebsitePageContentSeeder::class);

        $this->putJson('/api/v1/website/admin/settings', [
            'site_name' => 'RoyalCare Clinic',
            'hero_title' => 'Written from the wrong place',
            'hero_subtitle' => 'Written from the wrong place',
            'about_summary' => 'Written from the wrong place',
        ])->assertOk()
            ->assertJsonPath('data.site_name', 'RoyalCare Clinic');

        $settings = WebsiteSetting::current();
        $this->assertNull($settings->hero_title);
        $this->assertNull($settings->hero_subtitle);
        $this->assertNull($settings->about_summary);

        // The homepage copy keeps its home in the content blocks.
        $this->assertDatabaseHas('content_blocks', ['group' => 'home', 'key' => 'home_hero_title']);
        $this->assertDatabaseHas('content_blocks', ['group' => 'home', 'key' => 'home_about_text']);
        $this->assertDatabaseHas('content_blocks', ['group' => 'footer', 'key' => 'footer_about_text']);
    }

    public function test_the_settings_endpoint_does_not_expose_the_retired_homepage_columns(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        // A database that still holds the legacy values must not publish them.
        WebsiteSetting::current()->forceFill([
            'hero_title' => 'Legacy headline',
            'hero_subtitle' => 'Legacy subtitle',
            'about_summary' => 'Legacy blurb',
        ])->save();

        $payload = $this->getJson('/api/v1/website/admin/settings')->assertOk()->json('data');

        foreach (['hero_title', 'hero_subtitle', 'about_summary', 'hero_media_id'] as $retired) {
            $this->assertArrayNotHasKey($retired, $payload);
        }
    }

    public function test_saving_a_homepage_field_updates_the_public_homepage(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->seed(WebsitePageContentSeeder::class);

        $this->postJson('/api/v1/website/admin/content-blocks/bulk', [
            'blocks' => [
                ['key' => 'home_hero_title', 'group' => 'home', 'body' => 'Care that feels close to home.'],
                ['key' => 'home_hero_subtitle', 'group' => 'home', 'body' => 'Round-the-clock care for every family.'],
            ],
        ])->assertOk();

        $blocks = collect($this->getJson('/api/v1/website/bootstrap')->assertOk()->json('content_blocks'))
            ->keyBy('key');

        $this->assertSame('Care that feels close to home.', $blocks['home_hero_title']['body']);
        $this->assertSame('Round-the-clock care for every family.', $blocks['home_hero_subtitle']['body']);
    }

    public function test_admin_can_edit_the_homepage_copy_as_content_blocks(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->postJson('/api/v1/website/admin/content-blocks/bulk', [
            'blocks' => [
                ['key' => 'home_hero_title', 'group' => 'home', 'body' => 'World-class care, close to home.'],
                ['key' => 'home_hero_subtitle', 'group' => 'home', 'body' => 'From everyday check-ups to specialist treatment.'],
                ['key' => 'home_about_text', 'group' => 'home', 'body' => 'A team that listens.'],
                ['key' => 'footer_about_text', 'group' => 'footer', 'body' => 'Footer blurb.'],
            ],
        ])->assertOk();

        $this->assertSame(
            'World-class care, close to home.',
            ContentBlock::where('key', 'home_hero_title')->where('group', 'home')->value('body')
        );
        $this->assertSame(
            'A team that listens.',
            ContentBlock::where('key', 'home_about_text')->where('group', 'home')->value('body')
        );
        $this->assertSame(
            'Footer blurb.',
            ContentBlock::where('key', 'footer_about_text')->where('group', 'footer')->value('body')
        );
    }

    public function test_bulk_update_keeps_the_text_of_image_blocks(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $block = ContentBlock::create([
            'group' => 'home',
            'key' => 'home_hero_image',
            'title' => 'Homepage hero image',
            'body' => 'Kept copy',
        ]);

        $this->postJson('/api/v1/website/admin/content-blocks/bulk', [
            'blocks' => [['key' => 'home_hero_image', 'group' => 'home', 'title' => 'Homepage hero image']],
        ])->assertOk();

        $this->assertSame('Kept copy', $block->fresh()->body);
    }

    public function test_admin_can_bulk_update_and_create_content_blocks(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->seed(AboutContentBlockSeeder::class);

        $this->postJson('/api/v1/website/admin/content-blocks/bulk', [
            'blocks' => [
                ['key' => 'about_story_heading', 'body' => 'A whole new story heading'],
                ['key' => 'about_values', 'data' => [['title' => 'Kindness', 'text' => 'We care.']]],
                ['key' => 'about_brand_new_block', 'group' => 'about', 'body' => 'Created on the fly'],
            ],
        ])->assertOk();

        $this->assertSame(
            'A whole new story heading',
            ContentBlock::where('key', 'about_story_heading')->where('group', 'about')->value('body')
        );
        $this->assertSame(
            'Kindness',
            ContentBlock::where('key', 'about_values')->first()->data[0]['title']
        );
        $this->assertDatabaseHas('content_blocks', [
            'key' => 'about_brand_new_block',
            'group' => 'about',
            'body' => 'Created on the fly',
        ]);
    }

    public function test_non_admin_cannot_update_content_blocks(): void
    {
        Sanctum::actingAs($this->userWithRole('Nurse'));

        $this->postJson('/api/v1/website/admin/content-blocks/bulk', [
            'blocks' => [['key' => 'about_story_heading', 'body' => 'Nope']],
        ])->assertForbidden();
    }

    public function test_page_content_seeder_seeds_every_public_page_group_and_is_idempotent(): void
    {
        $this->seed(WebsitePageContentSeeder::class);

        $expectedKeys = [
            'header' => 'header_cta_label',
            'footer' => 'footer_quick_links_heading',
            'home' => 'home_cta_heading',
            'services' => 'services_heading',
            'team' => 'team_heading',
            'gallery' => 'gallery_heading',
            'news' => 'news_heading',
            'contact' => 'contact_heading',
        ];

        foreach ($expectedKeys as $group => $key) {
            $this->assertDatabaseHas('content_blocks', ['group' => $group, 'key' => $key]);
        }

        // The homepage copy that used to live in the website settings.
        $this->assertDatabaseHas('content_blocks', ['group' => 'home', 'key' => 'home_hero_title']);
        $this->assertDatabaseHas('content_blocks', ['group' => 'home', 'key' => 'home_hero_subtitle']);
        $this->assertDatabaseHas('content_blocks', ['group' => 'home', 'key' => 'home_hero_highlight']);
        $this->assertDatabaseHas('content_blocks', ['group' => 'home', 'key' => 'home_hero_image']);
        $this->assertDatabaseHas('content_blocks', ['group' => 'home', 'key' => 'home_about_text']);
        $this->assertDatabaseHas('content_blocks', ['group' => 'footer', 'key' => 'footer_about_text']);

        // Structured (list) content lands in the JSON data column.
        $this->assertSame(
            '10,000+',
            ContentBlock::where('key', 'home_stats')->where('group', 'home')->first()->data[0]['value']
        );

        // Re-seeding keeps admin edits and never duplicates rows.
        ContentBlock::where('key', 'header_cta_label')->update(['body' => 'Reserve a Visit']);
        $rowsBefore = ContentBlock::count();

        $this->seed(WebsitePageContentSeeder::class);

        $this->assertSame($rowsBefore, ContentBlock::count());
        $this->assertSame(
            'Reserve a Visit',
            ContentBlock::where('key', 'header_cta_label')->value('body')
        );
    }

    public function test_admin_can_store_khmer_translations_for_cms_content(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $pageId = $this->postJson('/api/v1/website/admin/pages', [
            'title' => 'Our History',
            'title_km' => 'ប្រវត្តិរបស់យើង',
            'body' => '<p>Founded in 2001.</p>',
            'body_km' => '<p>បង្កើតឡើងក្នុងឆ្នាំ 2001។</p>',
            'status' => 'published',
        ])->assertCreated()
            ->assertJsonPath('data.title_km', 'ប្រវត្តិរបស់យើង')
            ->json('data.id');

        $serviceId = $this->postJson('/api/v1/website/admin/services', [
            'title' => 'Pediatrics',
            'title_km' => 'កុមារវិទ្យា',
            'short_description' => 'Care for children.',
            'short_description_km' => 'ការថែទាំកុមារ។',
            'is_published' => true,
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/website/admin/gallery', [
            'title' => 'Reception',
            'title_km' => 'បន្ទប់ទទួលភ្ញៀវ',
            'image' => $this->fakePng('reception.png'),
        ])->assertCreated()->assertJsonPath('data.title_km', 'បន្ទប់ទទួលភ្ញៀវ');

        $this->postJson('/api/v1/website/admin/team', [
            'name' => 'Dr. Sokha',
            'position' => 'Cardiologist',
            'position_km' => 'វេជ្ជបណ្ឌិតជំងឺបេះដូង',
        ])->assertCreated()->assertJsonPath('data.position_km', 'វេជ្ជបណ្ឌិតជំងឺបេះដូង');

        $this->postJson('/api/v1/website/admin/articles', [
            'title' => 'Staying Hydrated',
            'title_km' => 'ការផឹកទឹកឱ្យបានគ្រប់គ្រាន់',
            'body' => '<p>Drink water.</p>',
            'body_km' => '<p>ផឹកទឹក។</p>',
        ])->assertCreated()->assertJsonPath('data.title_km', 'ការផឹកទឹកឱ្យបានគ្រប់គ្រាន់');

        $this->postJson('/api/v1/website/admin/content-blocks/bulk', [
            'blocks' => [[
                'key' => 'home_hero_title',
                'group' => 'home',
                'body' => 'World-class care',
                'body_km' => 'ការថែទាំកម្រិតពិភពលោក',
            ]],
        ])->assertOk();

        $this->putJson('/api/v1/website/admin/settings', [
            'site_name' => 'RoyalCare Clinic',
            'tagline' => 'Care you can trust',
            'tagline_km' => 'ការថែទាំដែលអ្នកអាចទុកចិត្ត',
            'address_km' => 'ភ្នំពេញ កម្ពុជា',
        ])->assertOk()
            ->assertJsonPath('data.tagline_km', 'ការថែទាំដែលអ្នកអាចទុកចិត្ត');

        $this->assertDatabaseHas('public_pages', ['id' => $pageId, 'title_km' => 'ប្រវត្តិរបស់យើង']);
        $this->assertDatabaseHas('services', ['id' => $serviceId, 'title_km' => 'កុមារវិទ្យា']);
        $this->assertDatabaseHas('content_blocks', [
            'key' => 'home_hero_title',
            'body_km' => 'ការថែទាំកម្រិតពិភពលោក',
        ]);
        $this->assertSame('ភ្នំពេញ កម្ពុជា', WebsiteSetting::current()->address_km);
    }

    public function test_khmer_fields_are_optional_and_do_not_break_english_only_content(): void
    {
        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->postJson('/api/v1/website/admin/services', [
            'title' => 'Dermatology',
            'is_published' => true,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Dermatology')
            ->assertJsonPath('data.title_km', null);
    }
}
