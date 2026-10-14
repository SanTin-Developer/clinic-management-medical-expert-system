<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class PrescriptionPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('prescriptions.view');
    }

    public function view(User $user, Prescription $prescription): bool
    {
        return $user->can('prescriptions.view')
            && $this->access->canAccessPatient($user, $prescription->patient_id);
    }

    public function create(User $user): bool
    {
        return $user->can('prescriptions.create');
    }

    public function update(User $user, Prescription $prescription): bool
    {
        return $user->can('prescriptions.update')
            && $this->access->canAccessPatient($user, $prescription->patient_id);
    }

    public function dispense(User $user, Prescription $prescription): bool
    {
        return $user->can('prescriptions.update')
            && $this->access->canAccessPatient($user, $prescription->patient_id);
    }

    public function delete(User $user, Prescription $prescription): bool
    {
        return $user->can('prescriptions.delete')
            && $this->access->canAccessPatient($user, $prescription->patient_id);
    }
}
