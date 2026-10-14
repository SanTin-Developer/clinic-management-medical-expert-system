<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A clinic employee in the staff directory. Exists independently of any system
 * User account: most staff (cleaners, guards, etc.) never log in, so user_id is
 * optional. Employment status is separate from the account lifecycle.
 */
class Staff extends Model
{
    use HasFactory, SoftDeletes;

    public const EMPLOYMENT_TYPE_FULL_TIME = 'full_time';

    public const EMPLOYMENT_TYPE_PART_TIME = 'part_time';

    public const EMPLOYMENT_TYPE_CONTRACT = 'contract';

    public const EMPLOYMENT_TYPE_INTERN = 'intern';

    public const STATUS_EMPLOYED = 'employed';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_RESIGNED = 'resigned';

    public const STATUS_TERMINATED = 'terminated';

    public const STATUS_PROBATION = 'probation';

    protected $fillable = [
        'user_id',
        'staff_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'phone',
        'email',
        'address',
        'photo_path',
        'emergency_contact_name',
        'emergency_contact_phone',
        'job_title',
        'department',
        'employment_type',
        'employment_status',
        'hire_date',
        'work_schedule',
        'supervisor_id',
        'education',
        'qualifications',
        'license_number',
        'license_expiry',
        'salary_amount',
        'pay_frequency',
        'allowances',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'license_expiry' => 'date',
            'salary_amount' => 'decimal:2',
            'allowances' => 'decimal:2',
        ];
    }

    protected $appends = [
        'full_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'supervisor_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Staff::class, 'supervisor_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StaffDocument::class);
    }

    public function salaryHistories(): HasMany
    {
        return $this->hasMany(StaffSalaryHistory::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(StaffInvitation::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function hasAccount(): bool
    {
        return $this->user_id !== null;
    }
}
