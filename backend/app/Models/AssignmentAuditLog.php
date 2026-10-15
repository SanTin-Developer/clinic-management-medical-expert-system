<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail for nurse assignment lifecycle events. Rows are only
 * written by the AssignmentAudit service and must never be updated or deleted
 * by the application.
 */
class AssignmentAuditLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTION_CREATED = 'created';

    public const ACTION_UPDATED = 'updated';

    public const ACTION_REASSIGNED = 'reassigned';

    public const ACTION_ENDED = 'ended';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'assignment_id',
        'action',
        'reason',
        'before',
        'after',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PatientAssignment::class, 'assignment_id');
    }
}
