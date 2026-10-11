<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('appointments.create');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.update');
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.cancel');
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.delete');
    }
}
