<?php

namespace App\Policies;

use App\Models\PatientAssignment;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class PatientAssignmentPolicy
{
    public function __construct(private readonly PatientAccessService $access) {}

    public function viewAny(User $user): bool
    {
        return $user->can('assignments.view');
    }

    public function view(User $user, PatientAssignment $assignment): bool
    {
        if (! $user->can('assignments.view')) {
            return false;
        }

        // A restricted nurse may only see their own assignments.
        if ($this->access->isRestricted($user)) {
            return (int) $assignment->nurse_id === (int) $user->id;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('assignments.manage');
    }

    public function delete(User $user, PatientAssignment $assignment): bool
    {
        return $user->can('assignments.manage');
    }
}
