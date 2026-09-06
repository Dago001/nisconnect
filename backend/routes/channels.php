<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// A user may listen on a conversation channel only if they are a member.
Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    $conversation = Conversation::find($conversationId);

    return $conversation !== null && $conversation->hasMember($user->id);
});

// Presence/private channel for a user's own events.
Broadcast::channel('user.{userId}', function (User $user, string $userId) {
    return $user->id === $userId;
});
