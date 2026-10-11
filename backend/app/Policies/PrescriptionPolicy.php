<?php

namespace App\Policies;

use App\Models\Prescription;
use App\Models\User;

class PrescriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('prescriptions.view');
    }

    public function view(User $user, Prescription $prescription): bool
    {
        return $user->can('prescriptions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('prescriptions.create');
    }

    public function update(User $user, Prescription $prescription): bool
    {
        return $user->can('prescriptions.update');
    }

    public function dispense(User $user, Prescription $prescription): bool
    {
        return $user->can('prescriptions.update');
    }

    public function delete(User $user, Prescription $prescription): bool
    {
        return $user->can('prescriptions.delete');
    }
}
