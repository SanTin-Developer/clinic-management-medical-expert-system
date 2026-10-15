<?php

namespace App\Services\Nursing;

use App\Models\MedicationAdministration;
use App\Models\NursingTask;
use App\Models\User;
use App\Notifications\NursingTaskAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NursingTaskService
{
    /**
     * Outcomes a nurse can record when completing a task that has no linked
     * clinical measurement record of its own.
     *
     * @var list<string>
     */
    public const OUTCOMES = [
        NursingTask::OUTCOME_COMPLETED,
        NursingTask::OUTCOME_PARTIALLY_COMPLETED,
        NursingTask::OUTCOME_HELD,
        NursingTask::OUTCOME_REFUSED,
        NursingTask::OUTCOME_NOT_COMPLETED,
    ];

    /**
     * Measurement fields required (at least one) to complete a vital signs task.
     *
     * @var list<string>
     */
    private const VITAL_MEASUREMENTS = [
        'temperature',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'heart_rate',
        'respiratory_rate',
        'oxygen_saturation',
    ];

    public function __construct(
        private readonly VitalSignService $vitals,
        private readonly MedicationAdministrationService $medications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): NursingTask
    {
        $task = NursingTask::create([
            'patient_id' => $data['patient_id'],
            'admission_id' => $data['admission_id'] ?? null,
            'assigned_nurse_id' => $data['assigned_nurse_id'] ?? null,
            'task_type' => $data['task_type'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'scheduled_at' => $data['scheduled_at'],
            'due_at' => $data['due_at'] ?? null,
            'status' => NursingTask::STATUS_PENDING,
            'notes' => $data['notes'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->notifyAssignee($task);

        return $task->load(['patient', 'assignedNurse', 'admission']);
    }

    public function assign(NursingTask $task, User $nurse, User $actor): NursingTask
    {
        if ($task->isCompleted()) {
            throw ValidationException::withMessages([
                'assigned_nurse_id' => 'A completed task cannot be reassigned.',
            ]);
        }

        $task->update(['assigned_nurse_id' => $nurse->id]);

        $this->notifyAssignee($task->fresh());

        return $task->fresh()->load(['patient', 'assignedNurse', 'admission']);
    }

    /**
     * Complete a task, persisting any required clinical record first.
     *
     * The task is only marked completed once the record it requires has been
     * saved. Everything happens in one transaction so a failure cannot leave the
     * task completed without its record (or vice versa).
     *
     * @param  array<string, mixed>  $payload
     */
    public function complete(NursingTask $task, User $actor, array $payload = []): NursingTask
    {
        return DB::transaction(function () use ($task, $actor, $payload) {
            $task = NursingTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($task->isCompleted()) {
                throw ValidationException::withMessages([
                    'status' => 'This nursing task is already completed.',
                ]);
            }

            if ($task->status === NursingTask::STATUS_CANCELLED) {
                throw ValidationException::withMessages([
                    'status' => 'A cancelled nursing task cannot be completed.',
                ]);
            }

            if ($task->requiresVitalSigns()) {
                $outcome = $this->completeWithVitalSigns($task, $actor, $payload);
            } elseif ($task->requiresMedicationAdministration()) {
                $outcome = $this->completeWithMedication($task, $actor, $payload);
            } else {
                $outcome = $this->resolveCareOutcome($payload);
            }

            $task->update([
                'status' => NursingTask::STATUS_COMPLETED,
                'completed_at' => now(),
                'completed_by' => $actor->id,
                'outcome' => $outcome,
                'outcome_reason' => $payload['reason'] ?? null,
                'notes' => $this->mergeNotes(
                    $task->notes,
                    $payload['care_provided'] ?? $payload['notes'] ?? null,
                ),
            ]);

            return $task->fresh()->load(['patient', 'assignedNurse', 'completer', 'vitalSign', 'medicationAdministration']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function completeWithVitalSigns(NursingTask $task, User $actor, array $payload): string
    {
        $hasMeasurement = collect(self::VITAL_MEASUREMENTS)
            ->contains(fn (string $field) => isset($payload[$field]) && $payload[$field] !== '' && $payload[$field] !== null);

        if (! $hasMeasurement) {
            throw ValidationException::withMessages([
                'temperature' => 'Enter at least one measurement taken from the patient.',
            ]);
        }

        $this->vitals->recordForTask($task, $actor, $payload);

        return $payload['outcome'] ?? NursingTask::OUTCOME_COMPLETED;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function completeWithMedication(NursingTask $task, User $actor, array $payload): string
    {
        $administration = $this->medications->resolveForTask(
            $task,
            isset($payload['medication_administration_id']) ? (int) $payload['medication_administration_id'] : null,
        );

        if ($administration === null) {
            throw ValidationException::withMessages([
                'medication_administration_id' => 'No scheduled medication dose is linked to this task.',
            ]);
        }

        if ($administration->nursing_task_id === null) {
            $administration->update(['nursing_task_id' => $task->id]);
        }

        $record = $this->medications->administer($administration, $actor, $payload);

        return match ($record->status) {
            MedicationAdministration::STATUS_ADMINISTERED => NursingTask::OUTCOME_COMPLETED,
            MedicationAdministration::STATUS_HELD => NursingTask::OUTCOME_HELD,
            MedicationAdministration::STATUS_REFUSED => NursingTask::OUTCOME_REFUSED,
            MedicationAdministration::STATUS_MISSED => NursingTask::OUTCOME_NOT_COMPLETED,
            default => NursingTask::OUTCOME_COMPLETED,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCareOutcome(array $payload): string
    {
        $outcome = $payload['outcome'] ?? NursingTask::OUTCOME_COMPLETED;

        if (! in_array($outcome, self::OUTCOMES, true)) {
            throw ValidationException::withMessages([
                'outcome' => 'Unsupported completion outcome.',
            ]);
        }

        if ($outcome !== NursingTask::OUTCOME_COMPLETED && trim((string) ($payload['reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when care was not fully completed.',
            ]);
        }

        return $outcome;
    }

    private function notifyAssignee(NursingTask $task): void
    {
        if ($task->assigned_nurse_id === null) {
            return;
        }

        $nurse = $task->assignedNurse;

        if ($nurse === null) {
            return;
        }

        $patient = $task->patient;

        $nurse->notify(new NursingTaskAssignedNotification(
            taskId: $task->id,
            patientId: $task->patient_id,
            patientName: $patient ? trim("{$patient->first_name} {$patient->last_name}") : 'Patient',
            title: $task->title,
            taskType: $task->task_type,
            priority: $task->priority,
            scheduledAt: $task->scheduled_at?->toIso8601String() ?? '',
        ));
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
