<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class WebsiteContactMessageTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_visitor_can_submit_contact_message(): void
    {
        $this->postJson('/api/v1/website/contact', [
            'name' => 'Sokha Chan',
            'email' => 'sokha@example.com',
            'phone' => '+85512345678',
            'subject' => 'Appointment question',
            'message' => 'Do you open on Sunday mornings?',
        ])->assertCreated()
            ->assertJsonPath('message', 'Thanks! Your message has been sent — we will get back to you soon.');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'sokha@example.com',
            'subject' => 'Appointment question',
            'read_at' => null,
        ]);
    }

    public function test_contact_message_requires_valid_payload(): void
    {
        $this->postJson('/api/v1/website/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'message' => '',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'message']);

        $this->postJson('/api/v1/website/contact', [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'message' => str_repeat('a', 5001),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_admin_can_list_and_filter_contact_messages(): void
    {
        ContactMessage::factory()->count(2)->create();
        ContactMessage::factory()->read()->create(['email' => 'read@example.com']);

        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->getJson('/api/v1/website/admin/contact-messages')
            ->assertOk()
            ->assertJsonPath('total', 3);

        $this->getJson('/api/v1/website/admin/contact-messages?status=unread')
            ->assertOk()
            ->assertJsonPath('total', 2);

        $this->getJson('/api/v1/website/admin/contact-messages?status=read')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.email', 'read@example.com');

        $this->getJson('/api/v1/website/admin/contact-messages?search=read@example.com')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_admin_can_mark_message_read_unread_and_delete(): void
    {
        $message = ContactMessage::factory()->create();

        Sanctum::actingAs($this->userWithRole('Admin'));

        $this->getJson('/api/v1/website/admin/contact-messages/'.$message->id)
            ->assertOk()
            ->assertJsonPath('data.id', $message->id);

        $this->postJson('/api/v1/website/admin/contact-messages/'.$message->id.'/read')
            ->assertOk()
            ->assertJsonPath('data.id', $message->id);

        $this->assertNotNull($message->fresh()->read_at);

        $this->postJson('/api/v1/website/admin/contact-messages/'.$message->id.'/unread')
            ->assertOk();

        $this->assertNull($message->fresh()->read_at);

        $this->deleteJson('/api/v1/website/admin/contact-messages/'.$message->id)
            ->assertOk();

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }

    public function test_non_admin_cannot_access_contact_inbox(): void
    {
        $message = ContactMessage::factory()->create();

        Sanctum::actingAs($this->userWithRole('Nurse'));

        $this->getJson('/api/v1/website/admin/contact-messages')->assertForbidden();
        $this->getJson('/api/v1/website/admin/contact-messages/'.$message->id)->assertForbidden();
        $this->postJson('/api/v1/website/admin/contact-messages/'.$message->id.'/read')->assertForbidden();
        $this->deleteJson('/api/v1/website/admin/contact-messages/'.$message->id)->assertForbidden();
    }

    public function test_guest_cannot_access_contact_inbox(): void
    {
        $this->getJson('/api/v1/website/admin/contact-messages')->assertUnauthorized();
    }
}
