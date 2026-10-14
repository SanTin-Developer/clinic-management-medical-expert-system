<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'new_message',
        'group_updates',
        'system_announcements',
        'telegram',
    ];

    protected function casts(): array
    {
        return [
            'new_message' => 'boolean',
            'group_updates' => 'boolean',
            'system_announcements' => 'boolean',
            'telegram' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(User $user): self
    {
        return static::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'new_message' => true,
                'group_updates' => true,
                'system_announcements' => true,
                'telegram' => true,
            ],
        );
    }

    public function allows(string $category): bool
    {
        return (bool) match ($category) {
            'new_message' => $this->new_message,
            'group_updates' => $this->group_updates,
            'system_announcements' => $this->system_announcements,
            default => true,
        };
    }
}
