<?php

namespace App\Policies;

use App\Models\SystemAlert;
use App\Models\User;
use App\Services\Nursing\PatientAccessService;

class SystemAlertPolicy
{
    public function __construct(private readonly PatientAccessService $access)
    {
    }

    public function viewAny(User $user): bool
    {
        return SystemAlert::visibleCategoriesFor($user) !== [];
    }

    public function view(User $user, SystemAlert $alert): bool
    {
        if (! SystemAlert::isCategoryVisibleTo($user, (string) $alert->category)) {
            return false;
        }

        if ($this->access->isRestricted($user)) {
            return $this->access->canAccessPatient($user, $alert->patient_id);
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('notifications.manage')
            && SystemAlert::visibleCategoriesFor($user) !== [];
    }

    public function update(User $user, SystemAlert $alert): bool
    {
        return $user->can('notifications.manage') && $this->view($user, $alert);
    }

    public function delete(User $user, SystemAlert $alert): bool
    {
        return $this->update($user, $alert);
    }
}
