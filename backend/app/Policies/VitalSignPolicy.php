<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VitalSign;

class VitalSignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vitals.view');
    }

    public function view(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('vitals.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vitals.create');
    }

    public function update(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('vitals.update');
    }

    public function delete(User $user, VitalSign $vitalSign): bool
    {
        return $user->can('vitals.delete');
    }
}
