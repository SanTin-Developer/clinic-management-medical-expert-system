<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramDeliveryLog extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'telegram_chat_id',
        'event_type',
        'subject',
        'status',
        'error',
        'attempts',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'attempts' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}