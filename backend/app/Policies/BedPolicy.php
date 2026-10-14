<?php

namespace App\Policies;

use App\Models\Bed;
use App\Models\User;

class BedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('beds.view');
    }

    public function view(User $user, Bed $bed): bool
    {
        return $user->can('beds.view');
    }

    public function create(User $user): bool
    {
        return $user->can('beds.manage');
    }

    public function update(User $user, Bed $bed): bool
    {
        return $user->can('beds.manage');
    }

    public function delete(User $user, Bed $bed): bool
    {
        return $user->can('beds.manage');
    }
}
