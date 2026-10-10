<?php

namespace App\Policies;

use App\Models\Consultation;
use App\Models\User;

class ConsultationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('consultations.view');
    }

    public function view(User $user, Consultation $consultation): bool
    {
        return $user->can('consultations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('consultations.create');
    }

    public function update(User $user, Consultation $consultation): bool
    {
        return $user->can('consultations.update');
    }
}
