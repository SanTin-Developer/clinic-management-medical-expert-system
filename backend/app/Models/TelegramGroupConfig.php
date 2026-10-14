<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramGroupConfig extends Model
{
    public const CATEGORY_NEW_MESSAGE = 'new_message';

    public const CATEGORY_GROUP_UPDATES = 'group_updates';

    public const CATEGORY_ANNOUNCEMENTS = 'announcements';

    public const CATEGORIES = [
        self::CATEGORY_NEW_MESSAGE,
        self::CATEGORY_GROUP_UPDATES,
        self::CATEGORY_ANNOUNCEMENTS,
    ];

    protected $fillable = [
        'chat_id',
        'is_enabled',
        'event_categories',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'is_enabled' => 'boolean',
            'event_categories' => 'array',
        ];
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'is_enabled' => false,
            'event_categories' => self::CATEGORIES,
        ]);
    }

    public function allows(string $category): bool
    {
        if (! $this->is_enabled || $this->chat_id === null) {
            return false;
        }

        return in_array($category, $this->event_categories ?? [], true);
    }
}
