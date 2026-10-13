<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    public const TYPE_PRIVATE = 'private';

    public const TYPE_GROUP = 'group';

    protected $fillable = [
        'type',
        'title',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['last_read_message_id', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function isGroup(): bool
    {
        return $this->type === self::TYPE_GROUP;
    }

    /**
     * Human-readable title for a given viewer: the group title, or the other
     * participant's name for a direct conversation.
     *
     * Expects `activeParticipants.user` (or `participants.user`) to be loaded
     * when called outside of a request that already eager-loaded them.
     */
    public function displayTitleFor(User $viewer): string
    {
        if ($this->isGroup()) {
            return (string) ($this->title ?? 'Group');
        }

        $participants = $this->relationLoaded('activeParticipants')
            ? $this->activeParticipants
            : ($this->relationLoaded('participants') ? $this->participants : $this->participants()->with('user')->get());

        $other = $participants
            ->first(fn (ConversationParticipant $participant) => $participant->user_id !== $viewer->id);

        return $other?->user?->name ?? 'Direct message';
    }

    public function hasActiveParticipant(User $user): bool
    {
        return $this->participants()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists();
    }
}
