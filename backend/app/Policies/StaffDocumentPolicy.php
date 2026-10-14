<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\StaffDocument;
use App\Models\User;

class StaffDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.document.view');
    }

    /**
     * Determine whether the user can list documents belonging to a staff profile.
     */
    public function viewForStaff(User $user, Staff $staff): bool
    {
        return $user->can('staff.document.view') || $staff->user_id === $user->id;
    }

    public function view(User $user, StaffDocument $doc): bool
    {
        return $user->can('staff.document.view') || ($doc->staff && $doc->staff->user_id === $user->id);
    }

    public function create(User $user, ?Staff $staff = null): bool
    {
        return $user->can('staff.document.upload') || $user->can('staff.update');
    }

    public function delete(User $user, StaffDocument $doc): bool
    {
        return $user->can('staff.document.delete');
    }
}
