<?php

namespace App\Policies;

use App\Models\ShiftTemplate;
use App\Models\User;

class ShiftTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('scheduling.view');
    }

    public function view(User $user, ShiftTemplate $template): bool
    {
        return $user->can('scheduling.view');
    }

    public function create(User $user): bool
    {
        return $user->can('scheduling.manage');
    }

    public function update(User $user, ShiftTemplate $template): bool
    {
        return $user->can('scheduling.manage');
    }

    public function delete(User $user, ShiftTemplate $template): bool
    {
        return $user->can('scheduling.manage');
    }
}
