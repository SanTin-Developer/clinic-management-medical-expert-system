<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramConnection extends Model
{
    protected $fillable = [
        'user_id',
        'telegram_chat_id',
        'telegram_username',
        'active_conversation_id',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'telegram_chat_id' => 'integer',
            'active_conversation_id' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activeConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'active_conversation_id');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->telegram_chat_id !== null;
    }
}
