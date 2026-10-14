<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    /**
     * Determine whether the user can view any staff profiles.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('staff.view');
    }

    /**
     * Determine whether the user can view a specific staff profile.
     */
    public function view(User $user, Staff $staff): bool
    {
        return $user->can('staff.view') || ($staff->user_id === $user->id);
    }

    /**
     * Determine whether the user can create staff profiles.
     */
    public function create(User $user): bool
    {
        return $user->can('staff.create');
    }

    /**
     * Determine whether the user can update a staff profile.
     */
    public function update(User $user, Staff $staff): bool
    {
        return $user->can('staff.update');
    }

    /**
     * Determine whether the user can delete a staff profile.
     */
    public function delete(User $user, Staff $staff): bool
    {
        return $user->can('staff.delete');
    }

    /**
     * Determine whether the user can view salary information.
     */
    public function viewSalary(User $user, ?Staff $staff = null): bool
    {
        return $user->can('staff.salary.view');
    }

    /**
     * Determine whether the user can update salary information.
     */
    public function updateSalary(User $user, Staff $staff): bool
    {
        return $user->can('staff.salary.update');
    }

    /**
     * Determine whether the user can link/unlink user accounts.
     */
    public function linkUser(User $user, Staff $staff): bool
    {
        return $user->can('staff.update');
    }
}
