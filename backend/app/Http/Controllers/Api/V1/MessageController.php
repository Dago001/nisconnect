<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Services\Messaging\MessageService;
use App\Events\UserTyping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messages)
    {
    }

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->hasMember($request->user()->id), 403);

        $page = $this->messages->history($conversation, (int) $request->integer('per_page', 30) ?: 30, $request->query('cursor'));

        return response()->json([
            'data' => MessageResource::collection($page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ]);
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $message = $this->messages->send(
            $request->user(),
            $conversation,
            $request->validated('type'),
            $request->validated('body'),
            $request->validated('reply_to_id'),
            $request->validated('attachments') ?? [],
        );

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    public function markRead(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->conversation->hasMember($request->user()->id), 403);
        $this->messages->markRead($request->user(), $message);

        return response()->json(['message' => 'Read.']);
    }

    public function react(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->conversation->hasMember($request->user()->id), 403);
        $data = $request->validate(['emoji' => ['required', 'string', 'max:16']]);

        MessageReaction::firstOrCreate([
            'message_id' => $message->id,
            'user_id' => $request->user()->id,
            'emoji' => $data['emoji'],
        ]);

        return response()->json(['message' => 'Reaction added.']);
    }

    public function destroy(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->sender_id === $request->user()->id, 403);
        $message->delete();

        return response()->json(['message' => 'Message deleted.']);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->hasMember($request->user()->id), 403);
        broadcast(new UserTyping($conversation->id, $request->user()->id))->toOthers();

        return response()->json(['ok' => true]);
    }
}
