<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function view(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->can('attendance.manage') || $attendanceRecord->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function clock(User $user): bool
    {
        return $user->can('attendance.clock');
    }

    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->can('attendance.manage');
    }

    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->can('attendance.manage');
    }

    public function approve(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->can('attendance.manage');
    }

    public function correct(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->can('attendance.correct');
    }

    public function export(User $user): bool
    {
        return $user->can('attendance.export');
    }
}
