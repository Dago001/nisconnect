<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Official announcement channels. Only publishers (authorised administrators)
 * may post; everyone else subscribes and reads. Distinct from group chats.
 */
class ChannelController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $channels = Channel::query()
            ->withCount('members')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'avatar_path']);

        $subscribed = ChannelMember::where('user_id', $request->user()->id)->pluck('channel_id')->all();

        return response()->json([
            'data' => $channels->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'members_count' => $c->members_count,
                'subscribed' => in_array($c->id, $subscribed, true),
            ]),
        ]);
    }

    public function show(Request $request, Channel $channel): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'is_publisher' => $this->isPublisher($request->user()->id, $channel),
        ]]);
    }

    public function posts(Request $request, Channel $channel): JsonResponse
    {
        $conversation = $this->conversationFor($channel);
        $page = $conversation->messages()->with('sender')
            ->orderByDesc('created_at')
            ->cursorPaginate(30, ['*'], 'cursor', $request->query('cursor'));

        return response()->json([
            'data' => collect($page->items())->map(fn (Message $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender' => $m->sender?->display_name,
                'created_at' => $m->created_at,
            ]),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function publish(Request $request, Channel $channel): JsonResponse
    {
        abort_unless($this->isPublisher($request->user()->id, $channel), 403,
            'Only authorised publishers can post to this channel.');

        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);
        $conversation = $this->conversationFor($channel);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'type' => 'text',
            'body' => $data['body'],
            'status' => Message::STATUS_SENT,
        ]);
        $conversation->update(['last_message_id' => $message->id, 'updated_at' => Carbon::now()]);

        $this->audit->log('channel.published', actorId: $request->user()->id,
            resourceType: 'channel', resourceId: $channel->id);

        return response()->json(['data' => ['id' => $message->id, 'body' => $message->body]], 201);
    }

    public function subscribe(Request $request, Channel $channel): JsonResponse
    {
        ChannelMember::firstOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $request->user()->id],
            ['role' => ChannelMember::ROLE_SUBSCRIBER],
        );

        return response()->json(['message' => 'Subscribed.']);
    }

    private function isPublisher(string $userId, Channel $channel): bool
    {
        return ChannelMember::where('channel_id', $channel->id)
            ->where('user_id', $userId)
            ->where('role', ChannelMember::ROLE_PUBLISHER)
            ->exists();
    }

    /**
     * Each channel is backed by a channel-type conversation for its posts.
     */
    private function conversationFor(Channel $channel): Conversation
    {
        return DB::transaction(function () use ($channel) {
            $conversation = Conversation::where('channel_id', $channel->id)->first();
            if (! $conversation) {
                $conversation = Conversation::create([
                    'type' => Conversation::TYPE_CHANNEL,
                    'title' => $channel->name,
                    'channel_id' => $channel->id,
                    'created_by' => $channel->created_by,
                ]);
            }

            return $conversation;
        });
    }
}
