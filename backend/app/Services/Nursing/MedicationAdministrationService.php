<?php

namespace App\Services\Nursing;

use App\Models\MedicationAdministration;
use App\Models\NursingTask;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
     * Routes the clinic supports for an administration event.
     *
     * @var list<string>
     */
    public const ROUTES = ['oral', 'iv', 'im', 'sc', 'topical', 'inhalation', 'other'];

    /**
     * Outcomes a nurse can record against a scheduled dose.
     *
     * @var list<string>
     */
    public const OUTCOMES = [
        MedicationAdministration::STATUS_ADMINISTERED,
        MedicationAdministration::STATUS_HELD,
        MedicationAdministration::STATUS_REFUSED,
        MedicationAdministration::STATUS_MISSED,
    ];

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

        $this->assertNotDuplicateScheduledDose($data);

        return MedicationAdministration::create([
            'patient_id' => $data['patient_id'],
            'admission_id' => $data['admission_id'] ?? null,
            'nursing_task_id' => $data['nursing_task_id'] ?? null,
            'prescription_id' => $data['prescription_id'] ?? null,
            'prescription_item_id' => $data['prescription_item_id'] ?? null,
            'medicine_id' => $data['medicine_id'] ?? null,
            'ordered_by' => $data['ordered_by'] ?? null,
            'dose' => $data['dose'] ?? null,
            'route' => $data['route'] ?? null,
            'route_detail' => $data['route_detail'] ?? null,
            'scheduled_at' => $data['scheduled_at'],
            'status' => MedicationAdministration::STATUS_SCHEDULED,
            'notes' => $data['notes'] ?? null,
        ])->load(['patient', 'medicine', 'prescription', 'admission', 'nursingTask']);
    }

    /**
     * Record the actual administration event for a scheduled dose.
     *
     * The server timestamp is authoritative: clients may not supply
     * administered_at. Doses held, refused or missed never carry a given dose.
     *
     * @param  array<string, mixed>  $data
     */
    public function administer(MedicationAdministration $administration, User $actor, array $data): MedicationAdministration
    {
        $outcome = $data['outcome'] ?? MedicationAdministration::STATUS_ADMINISTERED;

        if (! in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages([
                'outcome' => 'Unsupported medication administration outcome.',
            ]);
        }

        return DB::transaction(function () use ($administration, $actor, $outcome, $data) {
            $administration = MedicationAdministration::query()
                ->lockForUpdate()
                ->findOrFail($administration->id);

            $this->assertActionable($administration);

            if ($outcome === MedicationAdministration::STATUS_ADMINISTERED) {
                $this->applyAdministered($administration, $actor, $data);
            } else {
                $this->applyException($administration, $actor, $outcome, $data);
            }

            return $administration->fresh()->load(['patient', 'medicine', 'prescriptionItem', 'administeredBy']);
        });
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

        if ($status !== MedicationAdministration::STATUS_CANCELLED && trim((string) $notes) === '') {
            throw ValidationException::withMessages([
                'notes' => 'A reason is required when a medication is not administered.',
            ]);
        }

        $administration->update([
            'status' => $status,
            'administered_by' => $actor->id,
            'notes' => $this->mergeNotes($administration->notes, $notes),
        ]);

        return $administration->fresh()->load(['patient', 'medicine']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyAdministered(MedicationAdministration $administration, User $actor, array $data): void
    {
        $dose = isset($data['dose']) ? trim((string) $data['dose']) : '';

        if ($dose === '') {
            throw ValidationException::withMessages([
                'dose' => 'The actual dose given is required.',
            ]);
        }

        $route = $data['route'] ?? null;

        if (! in_array($route, self::ROUTES, true)) {
            throw ValidationException::withMessages([
                'route' => 'A valid administration route is required.',
            ]);
        }

        $routeDetail = $data['route_detail'] ?? null;

        if ($route === 'other' && trim((string) $routeDetail) === '') {
            throw ValidationException::withMessages([
                'route_detail' => 'Specify the administration route when "Other" is selected.',
            ]);
        }

        $prescribed = $administration->prescriptionItem?->dosage;
        $this->assertDoseCompatible($prescribed, $dose);

        $administration->update([
            'status' => MedicationAdministration::STATUS_ADMINISTERED,
            'administered_at' => now(),
            'administered_by' => $actor->id,
            'dose' => $dose,
            'route' => $route,
            'route_detail' => $route === 'other' ? $routeDetail : null,
            'notes' => $this->mergeNotes($administration->notes, $data['notes'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyException(MedicationAdministration $administration, User $actor, string $outcome, array $data): void
    {
        $reason = $data['notes'] ?? null;

        if (trim((string) $reason) === '') {
            throw ValidationException::withMessages([
                'notes' => 'A reason is required when a medication is held, refused or missed.',
            ]);
        }

        $administration->update([
            'status' => $outcome,
            'administered_at' => null,
            'administered_by' => $actor->id,
            'notes' => $this->mergeNotes($administration->notes, $reason),
        ]);
    }

    private function assertActionable(MedicationAdministration $administration): void
    {
        if ($administration->status !== MedicationAdministration::STATUS_SCHEDULED) {
            throw ValidationException::withMessages([
                'status' => 'Only a scheduled medication can be updated.',
            ]);
        }
    }

    /**
     * The actual dose must carry the same unit as the prescribed dose. The
     * prescription is never rewritten.
     */
    private function assertDoseCompatible(?string $prescribed, string $actual): void
    {
        if ($prescribed === null || trim($prescribed) === '') {
            return;
        }

        if (preg_match('/([a-zA-Z]{1,10})\.?\s*$/', trim($prescribed), $matches) !== 1) {
            return;
        }

        $unit = $matches[1];

        if (stripos($actual, $unit) === false) {
            throw ValidationException::withMessages([
                'dose' => "The dose unit must match the prescribed dose ({$prescribed}).",
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertNotDuplicateScheduledDose(array $data): void
    {
        $query = MedicationAdministration::query()
            ->where('patient_id', $data['patient_id'])
            ->where('scheduled_at', $data['scheduled_at']);

        if (! empty($data['prescription_item_id'])) {
            $query->where('prescription_item_id', $data['prescription_item_id']);
        } elseif (! empty($data['medicine_id'])) {
            $query->where('medicine_id', $data['medicine_id']);
        } else {
            $query->whereNull('medicine_id');
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'A medication record already exists for this scheduled dose.',
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

    /**
     * Resolve the scheduled dose a medication nursing task refers to. A task
     * linked directly to a MAR row wins; otherwise the earliest still-scheduled
     * dose for the linked prescription item (or medicine) is used.
     */
    public function resolveForTask(NursingTask $task, ?int $administrationId = null): ?MedicationAdministration
    {
        if ($administrationId !== null) {
            return MedicationAdministration::query()
                ->where('patient_id', $task->patient_id)
                ->whereKey($administrationId)
                ->first();
        }

        return MedicationAdministration::query()
            ->where('nursing_task_id', $task->id)
            ->latest('id')
            ->first()
            ?? MedicationAdministration::query()
                ->where('patient_id', $task->patient_id)
                ->where('status', MedicationAdministration::STATUS_SCHEDULED)
                ->where(function ($query) use ($task) {
                    $query->where('admission_id', $task->admission_id)
                        ->orWhereNull('admission_id');
                })
                ->orderBy('scheduled_at')
                ->first();
    }
}
