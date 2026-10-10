<?php

namespace App\Policies;

use App\Models\MedicalRecord;
use App\Models\User;

class MedicalRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('medical-records.view');
    }

    public function view(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->can('medical-records.view');
    }

    public function create(User $user): bool
    {
        return $user->can('medical-records.create');
    }

    public function update(User $user, MedicalRecord $medicalRecord): bool
    {
        return $user->can('medical-records.update');
    }
}
