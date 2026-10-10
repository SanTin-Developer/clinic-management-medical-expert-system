<?php

namespace App\Policies;

use App\Models\Diagnosis;
use App\Models\User;

class DiagnosisPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('diagnoses.view');
    }

    public function view(User $user, Diagnosis $diagnosis): bool
    {
        return $user->can('diagnoses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('diagnoses.create');
    }

    public function update(User $user, Diagnosis $diagnosis): bool
    {
        return $user->can('diagnoses.update');
    }
}
