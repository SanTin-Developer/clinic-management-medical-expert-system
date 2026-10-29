<?php

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leave.view');
    }

    public function view(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.approve')
            || $user->id === $leaveRequest->user_id;
    }

    public function create(User $user): bool
    {
        return $user->can('leave.create');
    }

    public function update(User $user, LeaveRequest $leaveRequest): bool
    {
        if (! $leaveRequest->isPending()) {
            return false;
        }

        return $user->can('leave.update') || $user->id === $leaveRequest->user_id;
    }

    public function delete(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->can('leave.delete')) {
            return true;
        }

        return $user->id === $leaveRequest->user_id && $leaveRequest->isPending();
    }

    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $user->can('leave.approve');
    }

    public function cancel(User $user, LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->isPending()
            && ($user->id === $leaveRequest->user_id || $user->can('leave.approve'));
    }
}
