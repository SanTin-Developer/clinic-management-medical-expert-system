<?php

namespace Database\Seeders;

use App\Models\Admission;
use App\Models\Bed;
use App\Models\MedicationAdministration;
use App\Models\Medicine;
use App\Models\NursingTask;
use App\Models\Patient;
use App\Models\PatientAssignment;
use App\Models\Prescription;
use App\Models\User;
use App\Services\Nursing\AdmissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Populates the Inpatient pages (Admissions, Nurse Assignments, Nursing Tasks
 * and the MAR) with realistic demo data built on top of the seeded wards/beds
 * and demo patients. Safe to re-run: every step is idempotent and it never
 * fabricates clinical values, only references existing records.
 */
class InpatientDemoDataSeeder extends Seeder
{
    private const ACTIVE_ADMISSION_COUNT = 8;

    /**
     * @var array<int, array{reason: string, diagnosis: string}>
     */
    private const STAYS = [
        ['reason' => 'Community-acquired pneumonia', 'diagnosis' => 'Lobar pneumonia, right lower lobe'],
        ['reason' => 'Acute appendicitis', 'diagnosis' => 'Acute non-perforated appendicitis, post-op day 1'],
        ['reason' => 'Term pregnancy for delivery', 'diagnosis' => 'Spontaneous vertex delivery, uncomplicated'],
        ['reason' => 'Dehydration and gastroenteritis', 'diagnosis' => 'Acute gastroenteritis with mild dehydration'],
        ['reason' => 'Chest pain evaluation', 'diagnosis' => 'Unstable angina, under cardiac monitoring'],
        ['reason' => 'Cellulitis of the left leg', 'diagnosis' => 'Lower limb cellulitis responding to IV antibiotics'],
        ['reason' => 'Asthma exacerbation', 'diagnosis' => 'Moderate acute asthma exacerbation'],
        ['reason' => 'Elective hernia repair', 'diagnosis' => 'Inguinal hernia repair, post-op observation'],
    ];

    public function run(): void
    {
        $doctors = User::role('Doctor')->orderBy('id')->get();
        $nurses = User::role('Nurse')->orderBy('id')->get();
        $admin = User::role('Admin')->first();
        $headNurse = User::role('Head Nurse')->first();

        if ($nurses->isEmpty() || $doctors->isEmpty()) {
            return;
        }

        $actor = $headNurse ?? $admin ?? $doctors->first();
        $attending = $doctors->first();

        $patients = Patient::orderBy('id')->get();
        if ($patients->count() < 2) {
            return;
        }

        $admitPatients = $patients->take(self::ACTIVE_ADMISSION_COUNT);
        $historyPatient = $patients->get(self::ACTIVE_ADMISSION_COUNT) ?? $patients->last();

        $availableBeds = Bed::query()
            ->where('status', Bed::STATUS_AVAILABLE)
            ->orderBy('room_id')
            ->orderBy('id')
            ->get()
            ->all();

        $admissionService = app(AdmissionService::class);

        $bedPointer = 0;

        foreach ($admitPatients as $index => $patient) {
            $admission = Admission::query()
                ->where('patient_id', $patient->id)
                ->where('status', Admission::STATUS_ADMITTED)
                ->first();

            if ($admission === null) {
                while (
                    $bedPointer < count($availableBeds)
                    && $availableBeds[$bedPointer]->status !== Bed::STATUS_AVAILABLE
                ) {
                    $bedPointer++;
                }

                if ($bedPointer >= count($availableBeds)) {
                    break;
                }

                $stay = self::STAYS[$index % count(self::STAYS)];

                $admission = $admissionService->admit([
                    'patient_id' => $patient->id,
                    'bed_id' => $availableBeds[$bedPointer]->id,
                    'attending_doctor_id' => $attending->id,
                    'admitted_at' => now()->subDays($index + 1)->setTime(9, 30),
                    'reason' => $stay['reason'],
                    'diagnosis_summary' => $stay['diagnosis'],
                    'notes' => 'Demo inpatient stay seeded for the nursing workflow.',
                ], $actor);

                $bedPointer++;
            }

            // Keep the bed consistent with the active admission on re-runs.
            if ($admission->bed_id) {
                Bed::query()
                    ->whereKey($admission->bed_id)
                    ->update(['status' => Bed::STATUS_OCCUPIED]);
            }

            $nurse = $nurses[$index % $nurses->count()];
            $this->assignNurse($admission, $nurse, $actor, $index);
            $this->createTasks($admission, $nurse, $actor, $index);
            $this->createMar($admission, $nurse, $attending, $index);
        }

        $this->assignWardCoverage($nurses->first(), $actor);
        $this->createDischargedAdmission($historyPatient, $attending, $actor);
    }

    private function assignNurse(Admission $admission, User $nurse, User $actor, int $index): void
    {
        $exists = PatientAssignment::query()
            ->where('patient_id', $admission->patient_id)
            ->where('status', PatientAssignment::STATUS_ACTIVE)
            ->exists();

        if ($exists) {
            return;
        }

        PatientAssignment::create([
            'nurse_id' => $nurse->id,
            'patient_id' => $admission->patient_id,
            'admission_id' => $admission->id,
            'shift' => $index % 2 === 0 ? 'day' : 'night',
            'status' => PatientAssignment::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'assigned_by' => $actor->id,
            'notes' => 'Demo assignment seeded for the nursing workflow.',
        ]);
    }

    private function assignWardCoverage(User $nurse, User $actor): void
    {
        $admission = Admission::query()
            ->where('status', Admission::STATUS_ADMITTED)
            ->whereNotNull('ward_id')
            ->first();

        if ($admission === null) {
            return;
        }

        $exists = PatientAssignment::query()
            ->where('nurse_id', $nurse->id)
            ->where('ward_id', $admission->ward_id)
            ->where('status', PatientAssignment::STATUS_ACTIVE)
            ->exists();

        if ($exists) {
            return;
        }

        PatientAssignment::create([
            'nurse_id' => $nurse->id,
            'ward_id' => $admission->ward_id,
            'shift' => 'day',
            'status' => PatientAssignment::STATUS_ACTIVE,
            'starts_at' => now()->subDay(),
            'ends_at' => null,
            'assigned_by' => $actor->id,
            'notes' => 'Demo ward coverage assignment.',
        ]);
    }

    private function createTasks(Admission $admission, User $nurse, User $actor, int $index): void
    {
        if ($admission->nursingTasks()->exists()) {
            return;
        }

        $base = now()->subHours($index % 3);

        $tasks = [
            [
                'task_type' => NursingTask::TYPE_VITAL_SIGNS,
                'title' => 'Record four-hourly vital signs',
                'description' => 'Temperature, pulse, respiration and blood pressure.',
                'priority' => 'normal',
                'scheduled_at' => $base->copy(),
                'due_at' => $base->copy()->addHour(),
                'status' => NursingTask::STATUS_PENDING,
            ],
            [
                'task_type' => NursingTask::TYPE_MEDICATION,
                'title' => 'Administer scheduled medication',
                'description' => 'Check MAR and administer the due dose.',
                'priority' => 'high',
                'scheduled_at' => $base->copy(),
                'due_at' => $base->copy()->addMinutes(30),
                'status' => NursingTask::STATUS_IN_PROGRESS,
            ],
            [
                'task_type' => NursingTask::TYPE_WOUND_CARE,
                'title' => 'Review and redress wound site',
                'description' => 'Inspect for signs of infection and renew dressing.',
                'priority' => 'normal',
                'scheduled_at' => $base->copy()->subHours(4),
                'due_at' => $base->copy()->subHours(2),
                'status' => NursingTask::STATUS_PENDING,
            ],
            [
                'task_type' => NursingTask::TYPE_OBSERVATION,
                'title' => 'Hourly observation round',
                'description' => 'Pain score, mobility and fluid balance check.',
                'priority' => 'low',
                'scheduled_at' => $base->copy()->subHour(),
                'due_at' => $base->copy()->subMinutes(30),
                'status' => NursingTask::STATUS_COMPLETED,
                'completed_at' => $base->copy()->subMinutes(45),
            ],
        ];

        foreach ($tasks as $task) {
            $completed = $task['status'] === NursingTask::STATUS_COMPLETED;

            $admission->nursingTasks()->create([
                'patient_id' => $admission->patient_id,
                'assigned_nurse_id' => $nurse->id,
                'task_type' => $task['task_type'],
                'title' => $task['title'],
                'description' => $task['description'],
                'priority' => $task['priority'],
                'scheduled_at' => $task['scheduled_at'],
                'due_at' => $task['due_at'],
                'completed_at' => $task['completed_at'] ?? null,
                'completed_by' => $completed ? $nurse->id : null,
                'status' => $task['status'],
                'created_by' => $actor->id,
            ]);
        }
    }

    private function createMar(Admission $admission, User $nurse, User $attending, int $index): void
    {
        if ($admission->medicationAdministrations()->exists()) {
            return;
        }

        $prescription = Prescription::query()
            ->where('patient_id', $admission->patient_id)
            ->with('items.medicine')
            ->latest('id')
            ->first();

        /** @var Collection<int, array{medicine_id: int|null, prescription_id: int|null, prescription_item_id: int|null, dose: string, route: string}> $sources */
        $sources = collect();

        if ($prescription !== null) {
            foreach ($prescription->items as $item) {
                $sources->push([
                    'medicine_id' => $item->medicine_id,
                    'prescription_id' => $prescription->id,
                    'prescription_item_id' => $item->id,
                    'dose' => $item->dosage ?: '1 dose',
                    'route' => $this->routeFor($item->medicine?->form),
                ]);
            }
        }

        if ($sources->isEmpty()) {
            $medicines = Medicine::query()->orderBy('id')->skip($index % 3)->take(2)->get();
            foreach ($medicines as $medicine) {
                $sources->push([
                    'medicine_id' => $medicine->id,
                    'prescription_id' => null,
                    'prescription_item_id' => null,
                    'dose' => $medicine->strength ? '1 x '.$medicine->strength : '1 dose',
                    'route' => $this->routeFor($medicine->form),
                ]);
            }
        }

        $sources = $sources->values();

        $medicationTask = $admission->nursingTasks()
            ->where('task_type', NursingTask::TYPE_MEDICATION)
            ->first();

        $entries = [
            [
                'status' => MedicationAdministration::STATUS_ADMINISTERED,
                'scheduled_at' => now()->subHours(3 + ($index % 2)),
                'administered_at' => now()->subHours(3)->addMinutes(10),
                'administered_by' => $nurse->id,
            ],
            [
                'status' => MedicationAdministration::STATUS_SCHEDULED,
                'scheduled_at' => now()->addHours(3),
                'administered_at' => null,
                'administered_by' => null,
            ],
            [
                'status' => MedicationAdministration::STATUS_MISSED,
                'scheduled_at' => now()->subHours(8),
                'administered_at' => null,
                'administered_by' => null,
            ],
            [
                'status' => MedicationAdministration::STATUS_HELD,
                'scheduled_at' => now()->subHours(5),
                'administered_at' => null,
                'administered_by' => null,
            ],
        ];

        foreach ($entries as $entryIndex => $entry) {
            $source = $sources[$entryIndex % $sources->count()];

            $admission->medicationAdministrations()->create([
                'patient_id' => $admission->patient_id,
                'nursing_task_id' => $entry['status'] === MedicationAdministration::STATUS_SCHEDULED
                    ? $medicationTask?->id
                    : null,
                'prescription_id' => $source['prescription_id'],
                'prescription_item_id' => $source['prescription_item_id'],
                'medicine_id' => $source['medicine_id'],
                'ordered_by' => $attending->id,
                'administered_by' => $entry['administered_by'],
                'dose' => $source['dose'],
                'route' => $source['route'],
                'scheduled_at' => $entry['scheduled_at'],
                'administered_at' => $entry['administered_at'],
                'status' => $entry['status'],
                'notes' => $entry['status'] === MedicationAdministration::STATUS_HELD
                    ? 'Held pending review of latest observations.'
                    : null,
            ]);
        }
    }

    private function createDischargedAdmission(Patient $patient, User $attending, User $actor): void
    {
        $number = 'ADM-DEMO-DIS-0001';

        if (Admission::withTrashed()->where('admission_number', $number)->exists()) {
            return;
        }

        Admission::create([
            'admission_number' => $number,
            'patient_id' => $patient->id,
            'ward_id' => null,
            'bed_id' => null,
            'attending_doctor_id' => $attending->id,
            'status' => Admission::STATUS_DISCHARGED,
            'admitted_at' => now()->subDays(14)->setTime(11, 0),
            'discharged_at' => now()->subDays(11)->setTime(10, 15),
            'reason' => 'Short-stay observation',
            'diagnosis_summary' => 'Resolved chest pain, non-cardiac',
            'notes' => 'Demo historical admission for the list view.',
            'created_by' => $actor->id,
            'discharged_by' => $actor->id,
        ]);
    }

    private function routeFor(?string $form): string
    {
        $form = strtolower((string) $form);

        return match (true) {
            str_contains($form, 'inject') || str_contains($form, 'vial') => 'iv',
            str_contains($form, 'cream') || str_contains($form, 'ointment') || str_contains($form, 'gel') => 'topical',
            str_contains($form, 'inhal') || str_contains($form, 'spray') => 'inhalation',
            default => 'oral',
        };
    }
}
