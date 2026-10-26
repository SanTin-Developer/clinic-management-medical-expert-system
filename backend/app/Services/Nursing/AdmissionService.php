<?php

namespace App\Services\Nursing;

use App\Models\Admission;
use App\Models\AdmissionTransfer;
use App\Models\Bed;
use App\Models\MedicationAdministration;
use App\Models\NursingTask;
use App\Models\PatientAssignment;
use App\Models\User;
use App\Services\Inpatient\RoomChargeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionService
{
    /**
     * Admit a patient into a bed. Occupies the bed and records the acting user.
     *
     * @param  array<string, mixed>  $data
     */
    public function admit(array $data, User $actor): Admission
    {
        return DB::transaction(function () use ($data, $actor) {
            $bed = Bed::query()->lockForUpdate()->findOrFail($data['bed_id']);

            if (! $bed->isAvailable()) {
                throw ValidationException::withMessages([
                    'bed_id' => "Bed {$bed->label} is not available (currently {$bed->status}).",
                ]);
            }

            $alreadyAdmitted = Admission::query()
                ->where('patient_id', $data['patient_id'])
                ->where('status', Admission::STATUS_ADMITTED)
                ->exists();

            if ($alreadyAdmitted) {
                throw ValidationException::withMessages([
                    'patient_id' => 'This patient already has an active admission.',
                ]);
            }

            $bed->loadMissing('room');
            $wardId = $bed->room?->ward_id;

            $admission = Admission::create([
                'admission_number' => $this->generateAdmissionNumber(),
                'patient_id' => $data['patient_id'],
                'ward_id' => $wardId,
                'bed_id' => $bed->id,
                'attending_doctor_id' => $data['attending_doctor_id'] ?? null,
                'status' => Admission::STATUS_ADMITTED,
                'admitted_at' => $data['admitted_at'] ?? now(),
                'reason' => $data['reason'] ?? null,
                'diagnosis_summary' => $data['diagnosis_summary'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $bed->update(['status' => Bed::STATUS_OCCUPIED]);

            // Generate room charge for admission day
            try {
                app(RoomChargeService::class)->generateDailyCharges($admission->fresh(), $actor);
            } catch (\Throwable $e) {
                // Don't fail admission if room charge generation fails
            }

            return $admission->load(['patient', 'ward', 'bed', 'attendingDoctor']);
        });
    }

    /**
     * Discharge an admitted patient. Frees the bed, ends active assignments and
     * cancels any still-pending care, while keeping all history intact.
     */
    public function discharge(Admission $admission, User $actor, array $data = []): Admission
    {
        if (! $admission->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Only an currently admitted patient can be discharged.',
            ]);
        }

        return DB::transaction(function () use ($admission, $actor, $data) {
            if ($admission->bed_id) {
                Bed::query()
                    ->whereKey($admission->bed_id)
                    ->where('status', Bed::STATUS_OCCUPIED)
                    ->update(['status' => Bed::STATUS_AVAILABLE]);
            }

            $admission->assignments()
                ->where('status', PatientAssignment::STATUS_ACTIVE)
                ->update([
                    'status' => PatientAssignment::STATUS_ENDED,
                    'ends_at' => now(),
                    'ended_by' => $actor->id,
                    'ended_reason' => 'Patient discharged',
                ]);

            $admission->nursingTasks()
                ->whereIn('status', [NursingTask::STATUS_PENDING, NursingTask::STATUS_IN_PROGRESS])
                ->update(['status' => NursingTask::STATUS_CANCELLED]);

            $admission->medicationAdministrations()
                ->where('status', MedicationAdministration::STATUS_SCHEDULED)
                ->update(['status' => MedicationAdministration::STATUS_CANCELLED]);

            $admission->update([
                'status' => Admission::STATUS_DISCHARGED,
                'discharged_at' => $data['discharged_at'] ?? now(),
                'discharged_by' => $actor->id,
                'notes' => $this->appendNote($admission->notes, $data['notes'] ?? null),
            ]);

            return $admission->fresh()->load(['patient', 'ward', 'bed', 'attendingDoctor']);
        });
    }

    /**
     * Cancel an admission recorded in error. Frees the bed and cancels pending
     * care while preserving the record for audit.
     */
    public function cancel(Admission $admission, User $actor, ?string $reason = null): Admission
    {
        if (! $admission->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Only a currently admitted patient can be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($admission, $actor, $reason) {
            if ($admission->bed_id) {
                Bed::query()
                    ->whereKey($admission->bed_id)
                    ->where('status', Bed::STATUS_OCCUPIED)
                    ->update(['status' => Bed::STATUS_AVAILABLE]);
            }

            $admission->assignments()
                ->where('status', PatientAssignment::STATUS_ACTIVE)
                ->update([
                    'status' => PatientAssignment::STATUS_ENDED,
                    'ends_at' => now(),
                    'ended_by' => $actor->id,
                    'ended_reason' => 'Admission cancelled',
                ]);

            $admission->nursingTasks()
                ->whereIn('status', [NursingTask::STATUS_PENDING, NursingTask::STATUS_IN_PROGRESS])
                ->update(['status' => NursingTask::STATUS_CANCELLED]);

            $admission->medicationAdministrations()
                ->where('status', MedicationAdministration::STATUS_SCHEDULED)
                ->update(['status' => MedicationAdministration::STATUS_CANCELLED]);

            $admission->update([
                'status' => Admission::STATUS_CANCELLED,
                'discharged_by' => $actor->id,
                'notes' => $this->appendNote($admission->notes, $reason),
            ]);

            return $admission->fresh()->load(['patient', 'ward', 'bed', 'attendingDoctor']);
        });
    }

    /**
     * Move an admitted patient to a different bed/ward.
     */
    public function transfer(Admission $admission, Bed $newBed, User $actor, ?string $reason = null): Admission
    {
        if (! $admission->isActive()) {
            throw ValidationException::withMessages([
                'status' => 'Only a currently admitted patient can be transferred.',
            ]);
        }

        return DB::transaction(function () use ($admission, $newBed, $actor, $reason) {
            $newBed = Bed::query()->lockForUpdate()->findOrFail($newBed->id);

            if ($newBed->id === $admission->bed_id) {
                throw ValidationException::withMessages([
                    'bed_id' => 'Patient is already in the selected bed.',
                ]);
            }

            if (! $newBed->isAvailable()) {
                throw ValidationException::withMessages([
                    'bed_id' => "Bed {$newBed->label} is not available (currently {$newBed->status}).",
                ]);
            }

            $fromBedId = $admission->bed_id;
            $fromWardId = $admission->ward_id;

            if ($admission->bed_id) {
                Bed::query()
                    ->whereKey($admission->bed_id)
                    ->where('status', Bed::STATUS_OCCUPIED)
                    ->update(['status' => Bed::STATUS_AVAILABLE]);
            }

            $newBed->loadMissing('room');
            $newBed->update(['status' => Bed::STATUS_OCCUPIED]);

            $admission->update([
                'bed_id' => $newBed->id,
                'ward_id' => $newBed->room?->ward_id,
                'notes' => $this->appendNote($admission->notes, $reason),
            ]);

            // Record transfer history
            try {
                AdmissionTransfer::create([
                    'admission_id' => $admission->id,
                    'from_bed_id' => $fromBedId,
                    'to_bed_id' => $newBed->id,
                    'from_ward_id' => $fromWardId,
                    'to_ward_id' => $newBed->room?->ward_id,
                    'transferred_by' => $actor->id,
                    'reason' => $reason,
                    'transferred_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // ignore
            }

            // Generate room charge for new bed/day if needed
            try {
                app(RoomChargeService::class)->generateDailyCharges($admission->fresh(), $actor);
            } catch (\Throwable $e) {
                // ignore
            }

            return $admission->fresh()->load(['patient', 'ward', 'bed', 'attendingDoctor']);
        });
    }

    private function generateAdmissionNumber(): string
    {
        do {
            $number = 'ADM-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(2)));
        } while (Admission::withTrashed()->where('admission_number', $number)->exists());

        return $number;
    }

    private function appendNote(?string $existing, ?string $addition): ?string
    {
        if ($addition === null || trim($addition) === '') {
            return $existing;
        }

        return $existing === null || $existing === ''
            ? $addition
            : $existing."\n---\n".$addition;
    }
}
