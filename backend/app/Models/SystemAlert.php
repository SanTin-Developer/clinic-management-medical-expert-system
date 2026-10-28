<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SystemAlert extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_WARNING = 'warning';

    public const SEVERITY_INFO = 'info';

    public const SEVERITIES = [
        self::SEVERITY_CRITICAL,
        self::SEVERITY_WARNING,
        self::SEVERITY_INFO,
    ];

    public const SEVERITY_RANK = [
        self::SEVERITY_INFO => 1,
        self::SEVERITY_WARNING => 2,
        self::SEVERITY_CRITICAL => 3,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_RESOLVED = 'resolved';

    public const CATEGORY_INVENTORY = 'inventory';

    public const CATEGORY_INPATIENT_BILLING = 'inpatient_billing';

    public const CATEGORY_APPOINTMENTS = 'appointments';

    public const CATEGORY_STAFF = 'staff';

    public const CATEGORY_ATTENDANCE = 'attendance';

    public const CATEGORY_CLINICAL = 'clinical';

    public const CATEGORY_SYSTEM = 'system';

    public const CATEGORIES = [
        self::CATEGORY_INVENTORY,
        self::CATEGORY_INPATIENT_BILLING,
        self::CATEGORY_APPOINTMENTS,
        self::CATEGORY_STAFF,
        self::CATEGORY_ATTENDANCE,
        self::CATEGORY_CLINICAL,
        self::CATEGORY_SYSTEM,
    ];

    /**
     * Category visibility is permission-driven. A user only sees alert
     * categories they are already allowed to work with, so an alert can never
     * widen access to inventory, inpatient billing, staff, or clinical data.
     *
     * @var array<string, string>
     */
    public const CATEGORY_PERMISSIONS = [
        self::CATEGORY_INVENTORY => 'inventory.view',
        self::CATEGORY_INPATIENT_BILLING => 'inpatient.billing.view',
        self::CATEGORY_APPOINTMENTS => 'appointments.view',
        self::CATEGORY_STAFF => 'staff.view',
        self::CATEGORY_ATTENDANCE => 'attendance.view',
        self::CATEGORY_CLINICAL => 'inpatient.nursing.view',
        self::CATEGORY_SYSTEM => 'system.health.view',
    ];

    protected $fillable = [
        'source_key',
        'category',
        'patient_id',
        'severity',
        'title',
        'message',
        'module',
        'url',
        'data',
        'status',
        'read_at',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (SystemAlert $alert) {
            if (empty($alert->{$alert->getKeyName()})) {
                $alert->{$alert->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_ACKNOWLEDGED]);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForCategories(Builder $query, array $categories): Builder
    {
        if ($categories === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('category', $categories);
    }

    public static function permissionForCategory(string $category): ?string
    {
        return self::CATEGORY_PERMISSIONS[$category] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function visibleCategoriesFor(User $user): array
    {
        return array_values(array_filter(
            self::CATEGORIES,
            fn (string $category) => self::isCategoryVisibleTo($user, $category),
        ));
    }

    public static function isCategoryVisibleTo(User $user, string $category): bool
    {
        $permission = self::permissionForCategory($category);

        return $permission !== null && $user->can($permission);
    }

    /**
     * @return array<int, string>
     */
    public static function alertPermissions(): array
    {
        return array_values(array_unique(array_values(self::CATEGORY_PERMISSIONS)));
    }
}
