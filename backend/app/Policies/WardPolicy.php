<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Ward;

class WardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('wards.view');
    }

    public function view(User $user, Ward $ward): bool
    {
        return $user->can('wards.view');
    }

    public function create(User $user): bool
    {
        return $user->can('wards.manage');
    }

    public function update(User $user, Ward $ward): bool
    {
        return $user->can('wards.manage');
    }

    public function delete(User $user, Ward $ward): bool
    {
        return $user->can('wards.manage');
    }
}
