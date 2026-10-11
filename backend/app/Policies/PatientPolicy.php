<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Determine whether the user can view any patients.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('patients.view');
    }

    /**
     * Determine whether the user can view a patient.
     */
    public function view(User $user, Patient $patient): bool
    {
        return $user->can('patients.view');
    }

    /**
     * Determine whether the user can create patients.
     */
    public function create(User $user): bool
    {
        return $user->can('patients.create');
    }

    /**
     * Determine whether the user can update the patient.
     */
    public function update(User $user, Patient $patient): bool
    {
        return $user->can('patients.update');
    }

    /**
     * Determine whether the user can delete the patient.
     */
    public function delete(User $user, Patient $patient): bool
    {
        return $user->can('patients.delete');
    }

    /**
     * Determine whether the user can restore the patient.
     */
    public function restore(User $user, Patient $patient): bool
    {
        return $user->can('patients.restore');
    }

    /**
     * Determine whether the user can permanently delete the patient.
     */
    public function forceDelete(User $user, Patient $patient): bool
    {
        return $user->can('patients.forceDelete');
    }
}
