<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\QueueEntry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class QueueEntryTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function appointment(array $attributes = []): Appointment
    {
        return Appointment::create(array_merge([
            'appointment_number' => 'APT-'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory()->create()->id,
            'doctor_id' => $this->userWithRole('Doctor')->id,
            'appointment_date' => now()->toDateString(),
            'appointment_time' => '09:00',
            'status' => 'scheduled',
        ], $attributes));
    }

    public function test_receptionist_can_add_a_patient_to_the_queue(): void
    {
        $this->actingAsRole('Receptionist');
        $patient = Patient::factory()->create();

        $this->postJson('/api/v1/queue-entries', ['patient_id' => $patient->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonPath('data.priority', 'normal')
            ->assertJsonPath('data.patient_id', $patient->id);

        $this->assertDatabaseHas('queue_entries', [
            'patient_id' => $patient->id,
            'status' => 'waiting',
        ]);
    }

    public function test_linking_an_appointment_populates_patient_and_doctor(): void
    {
        $this->actingAsRole('Receptionist');
        $appointment = $this->appointment();

        $this->postJson('/api/v1/queue-entries', ['appointment_id' => $appointment->id])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $appointment->patient_id)
            ->assertJsonPath('data.doctor_id', $appointment->doctor_id)
            ->assertJsonPath('data.appointment_id', $appointment->id);
    }

    public function test_same_appointment_cannot_enter_the_queue_twice_in_a_day(): void
    {
        $this->actingAsRole('Receptionist');
        $appointment = $this->appointment();

        $this->postJson('/api/v1/queue-entries', ['appointment_id' => $appointment->id])
            ->assertCreated();

        $this->postJson('/api/v1/queue-entries', ['appointment_id' => $appointment->id])
            ->assertStatus(422);
    }

    public function test_entry_requires_a_patient_or_an_appointment(): void
    {
        $this->actingAsRole('Receptionist');

        $this->postJson('/api/v1/queue-entries', [])->assertStatus(422);
    }

    public function test_index_defaults_to_todays_queue_with_stats(): void
    {
        $this->actingAsRole('Nurse');

        QueueEntry::factory()->create(['queue_date' => now()->toDateString()]);
        QueueEntry::factory()->create(['queue_date' => now()->toDateString(), 'priority' => 'urgent']);
        QueueEntry::factory()->create(['queue_date' => now()->subDay()->toDateString()]);

        $this->getJson('/api/v1/queue-entries')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('stats.total', 2)
            ->assertJsonPath('stats.waiting', 2)
            ->assertJsonPath('data.0.priority', 'urgent');
    }

    public function test_queue_can_be_filtered_by_status(): void
    {
        $this->actingAsRole('Nurse');

        QueueEntry::factory()->create(['queue_date' => now()->toDateString()]);
        QueueEntry::factory()->create(['queue_date' => now()->toDateString(), 'status' => 'done']);

        $this->getJson('/api/v1/queue-entries?status=done')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.status', 'done');
    }

    public function test_appointments_endpoint_flags_entries_already_queued(): void
    {
        $this->actingAsRole('Receptionist');

        $queued = $this->appointment();
        $free = $this->appointment();
        QueueEntry::factory()->create([
            'queue_date' => now()->toDateString(),
            'appointment_id' => $queued->id,
            'patient_id' => $queued->patient_id,
        ]);

        $response = $this->getJson('/api/v1/queue-entries/appointments')->assertOk();
        $data = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($data[$queued->id]['already_queued']);
        $this->assertFalse($data[$free->id]['already_queued']);
    }

    public function test_entry_can_move_through_the_call_serve_complete_flow(): void
    {
        $this->actingAsRole('Nurse');
        $entry = QueueEntry::factory()->create(['queue_date' => now()->toDateString()]);

        $this->postJson("/api/v1/queue-entries/{$entry->id}/call")
            ->assertOk()
            ->assertJsonPath('data.status', 'called');

        $this->postJson("/api/v1/queue-entries/{$entry->id}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'serving');

        $this->postJson("/api/v1/queue-entries/{$entry->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->assertDatabaseHas('queue_entries', [
            'id' => $entry->id,
            'status' => 'done',
        ]);
    }

    public function test_entry_can_be_skipped_and_cancelled(): void
    {
        $this->actingAsRole('Receptionist');

        $skipped = QueueEntry::factory()->create(['queue_date' => now()->toDateString()]);
        $cancelled = QueueEntry::factory()->create(['queue_date' => now()->toDateString()]);

        $this->postJson("/api/v1/queue-entries/{$skipped->id}/skip")
            ->assertOk()
            ->assertJsonPath('data.status', 'skipped');

        $this->postJson("/api/v1/queue-entries/{$cancelled->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_entry_priority_and_notes_can_be_updated(): void
    {
        $this->actingAsRole('Nurse');
        $entry = QueueEntry::factory()->create(['queue_date' => now()->toDateString()]);

        $this->putJson("/api/v1/queue-entries/{$entry->id}", [
            'priority' => 'urgent',
            'notes' => 'Chest pain, triage immediately.',
        ])
            ->assertOk()
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.notes', 'Chest pain, triage immediately.');
    }

    public function test_entry_can_be_removed(): void
    {
        $this->actingAsRole('Receptionist');
        $entry = QueueEntry::factory()->create(['queue_date' => now()->toDateString()]);

        $this->deleteJson("/api/v1/queue-entries/{$entry->id}")->assertOk();

        $this->assertDatabaseMissing('queue_entries', ['id' => $entry->id]);
    }

    public function test_patient_without_permission_cannot_view_the_queue(): void
    {
        Sanctum::actingAs($this->userWithRole('Patient'));

        $this->getJson('/api/v1/queue-entries')->assertForbidden();
    }

    public function test_unauthenticated_cannot_view_the_queue(): void
    {
        $this->getJson('/api/v1/queue-entries')->assertUnauthorized();
    }
}
