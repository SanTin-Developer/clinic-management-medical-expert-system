<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\PublicPage;
use App\Models\Service;
use App\Models\WebsiteSetting;
use App\Services\Website\WebsiteMediaStorage;
use Database\Seeders\AboutContentBlockSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
}
