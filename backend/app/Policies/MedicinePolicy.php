<?php

namespace App\Policies;

use App\Models\Medicine;
use App\Models\User;

class MedicinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('medicines.view');
    }

    public function view(User $user, Medicine $medicine): bool
    {
        return $user->can('medicines.view');
    }

    public function create(User $user): bool
    {
        return $user->can('medicines.create');
    }

    public function update(User $user, Medicine $medicine): bool
    {
        return $user->can('medicines.update');
    }

    public function delete(User $user, Medicine $medicine): bool
    {
        return $user->can('medicines.delete');
    }
}
