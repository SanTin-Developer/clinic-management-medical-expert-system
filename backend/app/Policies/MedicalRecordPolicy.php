<?php

namespace App\Policies;

use App\Models\MedicalRecord;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class MedicalRecordPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('medical-records.view');
    }

    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->can('medical-records.view')
            && $this->access->canAccessPatient($user, $medicalRecord->patient_id);
    }

    public function create(User $user): bool
    {
        return $user->can('medical-records.create');
    }

    public function update(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->can('medical-records.update')
            && $this->access->canAccessPatient($user, $medicalRecord->patient_id);
    }

    public function delete(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->can('medical-records.delete')
            && $this->access->canAccessPatient($user, $medicalRecord->patient_id);
    }
}
