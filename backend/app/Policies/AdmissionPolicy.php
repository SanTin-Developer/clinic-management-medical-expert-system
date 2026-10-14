<?php

namespace App\Policies;

use App\Models\Admission;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class AdmissionPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('admissions.view');
    }

    public function view(User $user, Admission $admission): bool
    {
        return $user->can('admissions.view')
            && $this->access->canAccessPatient($user, $admission->patient_id);
    }

    public function create(User $user): bool
    {
        return $user->can('admissions.create');
    }

    public function update(User $user, Admission $admission): bool
    {
        return $user->can('admissions.update')
            && $this->access->canAccessPatient($user, $admission->patient_id);
    }

    public function discharge(User $user, Admission $admission): bool
    {
        return $user->can('admissions.discharge')
            && $this->access->canAccessPatient($user, $admission->patient_id);
    }

    public function delete(User $user, Admission $admission): bool
    {
        return $user->can('admissions.update')
            && $this->access->canAccessPatient($user, $admission->patient_id);
    }
}
