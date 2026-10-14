<?php

namespace App\Services\Nursing;

use App\Models\MedicationAdministration;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Records inpatient medication administration.
 *
 * IMPORTANT: this service never mutates pharmacy inventory. Stock is decremented
 * exactly once when a prescription is dispensed; the MAR only documents that a
 * scheduled dose was given, held, refused or missed.
 */
class MedicationAdministrationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(array $data, User $actor): MedicationAdministration
    {
        $item = null;

        if (! empty($data['prescription_item_id'])) {
            $item = PrescriptionItem::query()->with('prescription')->findOrFail($data['prescription_item_id']);

            $data['prescription_id'] = $item->prescription_id;
            $data['medicine_id'] = $data['medicine_id'] ?? $item->medicine_id;
            $data['dose'] = $data['dose'] ?? $item->dosage;
            $data['patient_id'] = $data['patient_id'] ?? $item->prescription?->patient_id;
            $data['ordered_by'] = $data['ordered_by'] ?? $item->prescription?->doctor_id;

            if ($item->prescription && (int) $item->prescription->patient_id !== (int) $data['patient_id']) {
                throw ValidationException::withMessages([
                    'prescription_item_id' => 'The prescription item does not belong to the selected patient.',
                ]);
            }
        }

        return MedicationAdministration::create([
            'patient_id' => $data['patient_id'],
            'admission_id' => $data['admission_id'] ?? null,
            'prescription_id' => $data['prescription_id'] ?? null,
            'prescription_item_id' => $data['prescription_item_id'] ?? null,
            'medicine_id' => $data['medicine_id'] ?? null,
            'ordered_by' => $data['ordered_by'] ?? null,
            'dose' => $data['dose'] ?? null,
            'route' => $data['route'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'status' => MedicationAdministration::STATUS_SCHEDULED,
            'notes' => $data['notes'] ?? null,
        ])->load(['patient', 'medicine', 'prescription', 'admission']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function administer(MedicationAdministration $administration, User $actor, array $data = []): MedicationAdministration
    {
        $this->assertActionable($administration);

        $administration->update([
            'status' => MedicationAdministration::STATUS_ADMINISTERED,
            'administered_at' => $data['administered_at'] ?? now(),
            'administered_by' => $actor->id,
            'dose' => $data['dose'] ?? $administration->dose,
            'route' => $data['route'] ?? $administration->route,
            'notes' => $this->mergeNotes($administration->notes, $data['notes'] ?? null),
        ]);

        return $administration->fresh()->load(['patient', 'medicine', 'administeredBy']);
    }

    public function updateStatus(MedicationAdministration $administration, User $actor, string $status, ?string $notes = null): MedicationAdministration
    {
        $this->assertActionable($administration);

        $allowed = [
            MedicationAdministration::STATUS_HELD,
            MedicationAdministration::STATUS_REFUSED,
            MedicationAdministration::STATUS_MISSED,
            MedicationAdministration::STATUS_CANCELLED,
        ];

        if (! in_array($status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Unsupported medication administration status.',
            ]);
        }

        $administration->update([
            'status' => $status,
            'administered_by' => $actor->id,
            'notes' => $this->mergeNotes($administration->notes, $notes),
        ]);

        return $administration->fresh()->load(['patient', 'medicine']);
    }

    private function assertActionable(MedicationAdministration $administration): void
    {
        if ($administration->status !== MedicationAdministration::STATUS_SCHEDULED) {
            throw ValidationException::withMessages([
                'status' => 'Only a scheduled medication can be updated.',
            ]);
        }
    }

    private function mergeNotes(?string $existing, ?string $addition): ?string
    {
        if ($addition === null || trim($addition) === '') {
            return $existing;
        }

        return $existing === null || $existing === ''
            ? $addition
            : $existing."\n---\n".$addition;
    }
}
