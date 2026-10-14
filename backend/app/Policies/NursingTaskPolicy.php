<?php

namespace App\Policies;

use App\Models\NursingTask;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class NursingTaskPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('nursing-tasks.view');
    }

    public function view(User $user, NursingTask $task): bool
    {
        return $user->can('nursing-tasks.view')
            && $this->access->canAccessPatient($user, $task->patient_id);
    }

    public function create(User $user): bool
    {
        return $user->can('nursing-tasks.create');
    }

    public function update(User $user, NursingTask $task): bool
    {
        return $user->can('nursing-tasks.update')
            && $this->access->canAccessPatient($user, $task->patient_id);
    }

    public function complete(User $user, NursingTask $task): bool
    {
        return $user->can('nursing-tasks.complete')
            && $this->access->canAccessPatient($user, $task->patient_id);
    }

    public function delete(User $user, NursingTask $task): bool
    {
        return $user->can('nursing-tasks.update')
            && $this->access->canAccessPatient($user, $task->patient_id);
    }
}
