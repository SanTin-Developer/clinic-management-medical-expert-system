<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A rejected attendance QR scan. The code is a stable machine-readable reason;
 * the message is safe to surface to staff and is also written to the audit log.
 */
class AttendanceScanException extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $statusCode = 422,
    ) {
        parent::__construct($message);
    }

    public static function invalidToken(): self
    {
        return new self('invalid_token', 'This QR code is not valid.', 422);
    }

    public static function expiredToken(): self
    {
        return new self('expired_token', 'This QR code has expired. Scan the current code.', 422);
    }

    public static function revokedToken(): self
    {
        return new self('revoked_token', 'This QR code is no longer active.', 422);
    }

    public static function sessionClosed(): self
    {
        return new self('session_closed', 'This attendance session is closed.', 409);
    }

    public static function wrongAction(): self
    {
        return new self('wrong_action', 'This QR code is for a different action.', 422);
    }

    public static function notEligible(): self
    {
        return new self('not_eligible', 'Your account is not eligible to clock in.', 403);
    }

    public static function notAssigned(): self
    {
        return new self('not_assigned', 'You are not assigned to this shift.', 403);
    }

    public static function onLeave(): self
    {
        return new self('on_leave', 'You are on approved leave for this date.', 403);
    }

    public static function duplicate(string $message): self
    {
        return new self('duplicate', $message, 409);
    }

    public static function conflictingAttendance(): self
    {
        return new self('conflicting_attendance', 'An attendance record for this shift already exists.', 409);
    }
}
