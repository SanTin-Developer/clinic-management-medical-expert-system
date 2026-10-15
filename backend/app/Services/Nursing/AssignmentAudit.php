<?php

namespace App\Services\Nursing;

use App\Models\AssignmentAuditLog;
use App\Models\PatientAssignment;
use Illuminate\Http\Request;

/**
 * Writes append-only nurse assignment audit entries.
 *
 * Only non-sensitive assignment fields (identifiers, scope, schedule, status)
 * are stored as before/after values; clinical detail is never duplicated here.
 */
class AssignmentAudit
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        string $action,
        ?int $userId,
        ?PatientAssignment $assignment,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        ?Request $request = null,
    ): AssignmentAuditLog {
        return AssignmentAuditLog::create([
            'user_id' => $userId,
            'assignment_id' => $assignment?->id,
            'action' => $action,
            'reason' => $reason,
            'before' => $before ?: null,
            'after' => $after ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /**
     * A safe snapshot of an assignment for audit before/after values.
     *
     * @return array<string, mixed>
     */
    public function snapshot(PatientAssignment $assignment): array
    {
        return [
            'nurse_id' => $assignment->nurse_id,
            'patient_id' => $assignment->patient_id,
            'ward_id' => $assignment->ward_id,
            'admission_id' => $assignment->admission_id,
            'shift' => $assignment->shift,
            'status' => $assignment->status,
            'starts_at' => $assignment->starts_at?->toIso8601String(),
            'ends_at' => $assignment->ends_at?->toIso8601String(),
        ];
    }
}
