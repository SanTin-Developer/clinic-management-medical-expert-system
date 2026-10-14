<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class AppointmentPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('appointments.view');
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.view')
            && $this->access->canAccessPatient($user, $appointment->patient_id);
    }

    public function create(User $user): bool
    {
        return $user->can('appointments.create');
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.update')
            && $this->access->canAccessPatient($user, $appointment->patient_id);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.cancel')
            && $this->access->canAccessPatient($user, $appointment->patient_id);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->can('appointments.delete')
            && $this->access->canAccessPatient($user, $appointment->patient_id);
    }
}
