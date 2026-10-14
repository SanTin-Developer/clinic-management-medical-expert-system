<?php

namespace App\Services\Nursing;

use App\Models\NursingTask;
use App\Models\User;
use App\Notifications\NursingTaskAssignedNotification;
use Illuminate\Validation\ValidationException;

class NursingTaskService
{
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

    public function complete(NursingTask $task, User $actor, ?string $notes = null): NursingTask
    {
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

        $task->update([
            'status' => NursingTask::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $actor->id,
            'notes' => $notes !== null && trim($notes) !== ''
                ? trim(($task->notes ? $task->notes."\n---\n" : '').$notes)
                : $task->notes,
        ]);

        return $task->fresh()->load(['patient', 'assignedNurse', 'completer']);
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
}
