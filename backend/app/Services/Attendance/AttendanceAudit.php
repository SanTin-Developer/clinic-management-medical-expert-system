<?php

namespace App\Services\Attendance;

use App\Models\AttendanceAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Writes attendance/QR-session audit entries. Context is sanitised by the
 * callers: tokens and other secrets are never stored.
 */
class AttendanceAudit
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $event,
        ?int $userId = null,
        ?Model $auditable = null,
        array $context = [],
        ?Request $request = null,
    ): AttendanceAuditLog {
        return AttendanceAuditLog::create([
            'user_id' => $userId,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'context' => $context ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
