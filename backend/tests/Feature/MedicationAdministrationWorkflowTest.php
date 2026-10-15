<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\MedicationAdministration;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\PrescriptionItem;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class MedicationAdministrationWorkflowTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function scheduledDose(array $overrides = []): MedicationAdministration
    {
        return MedicationAdministration::factory()->create(array_merge([
            'patient_id' => Patient::factory(),
            'status' => MedicationAdministration::STATUS_SCHEDULED,
            'scheduled_at' => now(),
        ], $overrides));
    }

    private function actingNurseFor(Patient $patient): User
    {
        $nurse = $this->userWithRole('Nurse');

        PatientAssignment::create([
            'nurse_id' => $nurse->id,
            'patient_id' => $patient->id,
            'status' => PatientAssignment::STATUS_ACTIVE,
            'starts_at' => now(),
        ]);

        Sanctum::actingAs($nurse);

        return $nurse;
    }

    public function test_nurse_records_administration_with_server_timestamp(): void
    {
        $record = $this->scheduledDose();
        $nurse = $this->actingNurseFor($record->patient);

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'outcome' => 'administered',
            'dose' => '1 tablet',
            'route' => 'oral',
            'administered_at' => '2000-01-01T00:00:00+00:00',
        ])->assertOk()->assertJsonPath('data.status', MedicationAdministration::STATUS_ADMINISTERED);

        $fresh = $record->fresh();

        $this->assertSame(MedicationAdministration::STATUS_ADMINISTERED, $fresh->status);
        $this->assertSame($nurse->id, $fresh->administered_by);
        $this->assertNotNull($fresh->administered_at);
        $this->assertTrue($fresh->administered_at->isToday(), 'The server clock must be the source of administered_at.');
    }

    public function test_actual_dose_and_route_are_required_to_administer(): void
    {
        $record = $this->scheduledDose();
        $this->actingNurseFor($record->patient);

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'outcome' => 'administered',
        ])->assertStatus(422)->assertJsonValidationErrors('dose');

        $this->assertSame(MedicationAdministration::STATUS_SCHEDULED, $record->fresh()->status);
    }

    public function test_exception_outcomes_require_reason_and_clear_administered_at(): void
    {
        $record = $this->scheduledDose();
        $this->actingNurseFor($record->patient);

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'outcome' => 'held',
        ])->assertStatus(422)->assertJsonValidationErrors('notes');

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'outcome' => 'held',
            'notes' => 'Patient nauseous, dose held.',
        ])->assertOk()->assertJsonPath('data.status', MedicationAdministration::STATUS_HELD);

        $fresh = $record->fresh();

        $this->assertSame(MedicationAdministration::STATUS_HELD, $fresh->status);
        $this->assertNull($fresh->administered_at);
    }

    public function test_other_route_requires_detail(): void
    {
        $record = $this->scheduledDose();
        $this->actingNurseFor($record->patient);

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'dose' => '1 tablet',
            'route' => 'other',
        ])->assertStatus(422)->assertJsonValidationErrors('route_detail');

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'dose' => '1 tablet',
            'route' => 'other',
            'route_detail' => 'Nasogastric tube',
        ])->assertOk()->assertJsonPath('data.route_detail', 'Nasogastric tube');
    }

    public function test_actual_dose_unit_must_match_the_prescription(): void
    {
        $item = PrescriptionItem::factory()->create(['dosage' => '5 ml']);
        $record = $this->scheduledDose([
            'prescription_item_id' => $item->id,
            'medicine_id' => $item->medicine_id,
        ]);
        $this->actingNurseFor($record->patient);

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'dose' => '2 tablets',
            'route' => 'oral',
        ])->assertStatus(422)->assertJsonValidationErrors('dose');

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'dose' => '5 ml',
            'route' => 'oral',
        ])->assertOk();
    }

    public function test_a_scheduled_dose_cannot_be_administered_twice(): void
    {
        $record = $this->scheduledDose();
        $this->actingNurseFor($record->patient);

        $payload = ['dose' => '1 tablet', 'route' => 'oral'];

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", $payload)->assertOk();
        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", $payload)->assertStatus(422);
    }

    public function test_recording_administration_never_changes_inventory(): void
    {
        $record = $this->scheduledDose();
        $this->actingNurseFor($record->patient);

        $inventoryBefore = InventoryItem::query()->count();
        $movementsBefore = StockMovement::query()->count();

        $this->postJson("/api/v1/medication-administrations/{$record->id}/administer", [
            'dose' => '1 tablet',
            'route' => 'oral',
        ])->assertOk();

        $this->assertSame($inventoryBefore, InventoryItem::query()->count());
        $this->assertSame($movementsBefore, StockMovement::query()->count());
    }
}
