<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('wards.view');
    }

    public function view(User $user, Room $room): bool
    {
        return $user->can('wards.view');
    }

    public function create(User $user): bool
    {
        return $user->can('wards.manage');
    }

    public function update(User $user, Room $room): bool
    {
        return $user->can('wards.manage');
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->can('wards.manage');
    }
}
