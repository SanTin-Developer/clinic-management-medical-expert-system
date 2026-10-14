<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view a user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can invite staff members.
     */
    public function invite(User $user): bool
    {
        return $user->can('users.invite');
    }

    /**
     * Determine whether the user can approve or reject pending registrations.
     */
    public function approve(User $user): bool
    {
        return $user->can('users.approve');
    }

    /**
     * Determine whether the user can suspend, reactivate or disable accounts.
     */
    public function suspend(User $user): bool
    {
        return $user->can('users.suspend');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->can('users.update');
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->can('users.delete');
    }

    /**
     * Determine whether the user can restore the user.
     */
    public function restore(User $user, User $model): bool
    {
        return $user->can('users.update');
    }

    /**
     * Determine whether the user can permanently delete the user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $user->can('users.delete');
    }
}
