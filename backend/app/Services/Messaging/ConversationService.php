<?php

namespace App\Services\Messaging;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * Find or create the direct (1:1) conversation between two users.
     */
    public function directConversation(User $a, User $b): Conversation
    {
        $existing = Conversation::where('type', Conversation::TYPE_DIRECT)
            ->whereHas('members', fn ($q) => $q->where('user_id', $a->id))
            ->whereHas('members', fn ($q) => $q->where('user_id', $b->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($a, $b) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_DIRECT,
                'created_by' => $a->id,
            ]);

            foreach ([$a, $b] as $user) {
                ConversationMember::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $user->id,
                    'role' => 'member',
                    'joined_at' => Carbon::now(),
                ]);
            }

            return $conversation;
        });
    }

    /**
     * Conversations the user belongs to, most recently active first.
     */
    public function forUser(User $user, int $perPage = 20)
    {
        return Conversation::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $user->id)->whereNull('left_at'))
            ->with(['members.user.personnelRecord', 'lastMessage'])
            ->orderByDesc('updated_at')
            ->paginate($perPage);
    }
}
