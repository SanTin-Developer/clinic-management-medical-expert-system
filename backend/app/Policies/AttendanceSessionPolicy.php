<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\User;

/**
 * Only authorized administrators may open/close group QR sessions and view the
 * live session dashboard.
 */
class AttendanceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.session');
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        return $user->can('attendance.session');
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.session');
    }

    public function close(User $user, AttendanceSession $session): bool
    {
        return $user->can('attendance.session');
    }
}
