<?php

namespace App\Services\Messaging;

use App\Events\MessageCreated;
use App\Events\MessageRead as MessageReadEvent;
use App\Models\BlockedUser;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageRead;
use App\Models\User;
use App\Services\Push\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessageService
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * Send a message into a conversation. Enforces membership and blocking.
     *
     * @param  array<int, string>  $attachmentMediaIds
     */
    public function send(
        User $sender,
        Conversation $conversation,
        string $type,
        ?string $body,
        ?string $replyToId = null,
        array $attachmentMediaIds = [],
    ): Message {
        if (! $conversation->hasMember($sender->id)) {
            throw ValidationException::withMessages(['conversation' => 'You are not a member of this conversation.']);
        }

        // Blocking check for direct conversations.
        if ($conversation->type === Conversation::TYPE_DIRECT) {
            $other = $conversation->members()->where('user_id', '!=', $sender->id)->first();
            if ($other && $this->isBlockedBetween($sender->id, $other->user_id)) {
                throw ValidationException::withMessages(['conversation' => 'You cannot message this officer.']);
            }
        }

        $message = DB::transaction(function () use ($sender, $conversation, $type, $body, $replyToId, $attachmentMediaIds) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'type' => $type,
                'body' => $body,
                'reply_to_id' => $replyToId,
                'status' => Message::STATUS_SENT,
            ]);

            foreach ($attachmentMediaIds as $mediaId) {
                $message->attachments()->create([
                    'media_file_id' => $mediaId,
                    'kind' => $type,
                ]);
            }

            $conversation->forceFill([
                'last_message_id' => $message->id,
                'updated_at' => Carbon::now(),
            ])->save();

            return $message;
        });

        broadcast(new MessageCreated($message))->toOthers();

        // Notify other members (in-app + push). Recipients who have muted the
        // conversation are skipped.
        $preview = $type === 'text'
            ? \Illuminate\Support\Str::limit((string) $body, 120)
            : ucfirst($type).' message';
        $recipients = $conversation->members()
            ->where('user_id', '!=', $sender->id)
            ->whereNull('left_at')
            ->where(fn ($q) => $q->whereNull('muted_until')->orWhere('muted_until', '<', Carbon::now()))
            ->with('user')
            ->get();
        foreach ($recipients as $member) {
            if ($member->user) {
                $this->notifications->notify(
                    $member->user,
                    'message',
                    $sender->display_name,
                    $preview,
                    ['conversation_id' => $conversation->id, 'message_id' => $message->id],
                );
            }
        }

        return $message->load('attachments', 'sender');
    }

    /**
     * Cursor-paginated messages for a conversation (newest first).
     */
    public function history(Conversation $conversation, int $perPage = 30, ?string $cursor = null)
    {
        return $conversation->messages()
            ->with(['sender', 'attachments', 'reactions'])
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    /**
     * Full-text search across the user's own conversations using the GIN index.
     * Never loads a whole conversation onto the device — the DB does the work.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function search(User $user, string $term, ?string $conversationId = null, ?string $type = null, int $perPage = 20)
    {
        $memberConversationIds = $user->conversationMemberships()
            ->whereNull('left_at')
            ->pluck('conversation_id');

        return Message::query()
            ->whereIn('conversation_id', $memberConversationIds)
            ->when($conversationId, fn ($q) => $q->where('conversation_id', $conversationId))
            ->when($type, fn ($q) => $q->where('type', $type))
            ->whereRaw("to_tsvector('simple', coalesce(body, '')) @@ plainto_tsquery('simple', ?)", [$term])
            ->with(['sender', 'attachments'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Mark a message (and everything before it) read by a user.
     */
    public function markRead(User $user, Message $message): void
    {
        MessageRead::firstOrCreate(
            ['message_id' => $message->id, 'user_id' => $user->id],
            ['read_at' => Carbon::now()],
        );

        $message->conversation->members()
            ->where('user_id', $user->id)
            ->update(['last_read_message_id' => $message->id]);

        if ($message->sender_id && $message->sender_id !== $user->id) {
            $message->update(['status' => Message::STATUS_READ]);
            broadcast(new MessageReadEvent($message, $user->id))->toOthers();
        }
    }

    private function isBlockedBetween(string $a, string $b): bool
    {
        return BlockedUser::where(function ($q) use ($a, $b) {
            $q->where('blocker_id', $a)->where('blocked_id', $b);
        })->orWhere(function ($q) use ($a, $b) {
            $q->where('blocker_id', $b)->where('blocked_id', $a);
        })->exists();
    }
}
