<?php

namespace App\Policies;

use App\Models\ExpertRule;
use App\Models\User;

class ExpertRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('knowledge-base.view');
    }

    public function view(User $user, ExpertRule $expertRule): bool
    {
        return $user->can('knowledge-base.view');
    }

    public function create(User $user): bool
    {
        return $user->can('knowledge-base.manage');
    }

    public function update(User $user, ExpertRule $expertRule): bool
    {
        return $user->can('knowledge-base.manage');
    }

    public function delete(User $user, ExpertRule $expertRule): bool
    {
        return $user->can('knowledge-base.manage');
    }
}
