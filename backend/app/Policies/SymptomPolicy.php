<?php

namespace App\Policies;

use App\Models\Symptom;
use App\Models\User;

class SymptomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('knowledge-base.view');
    }

    public function view(User $user, Symptom $symptom): bool
    {
        return $user->can('knowledge-base.view');
    }

    public function create(User $user): bool
    {
        return $user->can('knowledge-base.manage');
    }

    public function update(User $user, Symptom $symptom): bool
    {
        return $user->can('knowledge-base.manage');
    }

    public function delete(User $user, Symptom $symptom): bool
    {
        return $user->can('knowledge-base.manage');
    }
}
