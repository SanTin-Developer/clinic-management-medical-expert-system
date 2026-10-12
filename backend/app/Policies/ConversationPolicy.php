<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('chat.view');
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->can('chat.view') && $conversation->hasActiveParticipant($user);
    }

    public function create(User $user): bool
    {
        return $user->can('chat.create');
    }

    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $user->can('chat.send') && $conversation->hasActiveParticipant($user);
    }

    public function manage(User $user, Conversation $conversation): bool
    {
        if (! $conversation->hasActiveParticipant($user)) {
            return false;
        }

        return $user->can('chat.manage') || (int) $conversation->created_by === (int) $user->id;
    }
}
