<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConversationMessage extends Model
{
    public const SOURCE_WEB = 'web';

    public const SOURCE_TELEGRAM = 'telegram';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'body',
        'source',
        'telegram_chat_id',
        'telegram_message_id',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'telegram_message_id' => 'integer',
        ];
    }

    public function isFromTelegram(): bool
    {
        return $this->source === self::SOURCE_TELEGRAM;
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(ConversationMessageRead::class, 'message_id');
    }
}
