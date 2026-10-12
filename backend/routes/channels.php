<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation) {
    return $conversation->participants()
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});

Broadcast::channel('notifications.{user}', function (User $user, User $target) {
    return (int) $user->id === (int) $target->id;
});
