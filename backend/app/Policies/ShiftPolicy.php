<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scheduling.view');
    }

    public function view(User $user, Shift $shift): bool
    {
        return $user->can('scheduling.manage') || $shift->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('scheduling.manage');
    }

    public function update(User $user, Shift $shift): bool
    {
        return $user->can('scheduling.manage');
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $user->can('scheduling.manage');
    }
}
