<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VitalSign;
use App\Services\Nursing\PatientAccessService;

class VitalSignPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('vitals.view');
    }

    public function view(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('vitals.view')
            && $this->access->canAccessPatient($user, $vitalSign->patient_id);
    }

    public function create(User $user): bool
    {
        return $user->can('vitals.create');
    }

    public function update(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('vitals.update')
            && $this->access->canAccessPatient($user, $vitalSign->patient_id);
    }

    public function delete(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('vitals.delete')
            && $this->access->canAccessPatient($user, $vitalSign->patient_id);
    }
}
