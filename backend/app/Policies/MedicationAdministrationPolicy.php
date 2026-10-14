<?php

namespace App\Policies;

use App\Models\MedicationAdministration;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class MedicationAdministrationPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('medications.view');
    }

    public function view(User $user, MedicationAdministration $administration): bool
    {
        return $user->can('medications.view')
            && $this->access->canAccessPatient($user, $administration->patient_id);
    }

    public function create(User $user): bool
    {
        return $user->can('medications.record');
    }

    public function administer(User $user, MedicationAdministration $administration): bool
    {
        return $user->can('medications.record')
            && $this->access->canAccessPatient($user, $administration->patient_id);
    }

    public function update(User $user, MedicationAdministration $administration): bool
    {
        return $this->administer($user, $administration);
    }

    public function delete(User $user, MedicationAdministration $administration): bool
    {
        return $user->can('medications.record')
            && $this->access->canAccessPatient($user, $administration->patient_id);
    }
}
