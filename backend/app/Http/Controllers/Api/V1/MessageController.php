<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\UserTyping;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Services\Messaging\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messages) {}

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
            [
                'duration_ms' => $request->validated('duration_ms'),
                'waveform' => $request->validated('waveform'),
            ],
        );

        return (new MessageResource($message))->response()->setStatusCode(201);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'conversation_id' => ['nullable', 'uuid'],
            'type' => ['nullable', 'in:text,image,video,document,audio,voice'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $page = $this->messages->search(
            $request->user(),
            $data['q'],
            $data['conversation_id'] ?? null,
            $data['type'] ?? null,
            $data['per_page'] ?? 20,
        );

        return response()->json([
            'data' => MessageResource::collection($page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
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

    public function update(Request $request, Message $message): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:8000']]);
        $updated = $this->messages->edit($request->user(), $message, $data['body']);

        return (new MessageResource($updated))->response();
    }

    public function pin(Request $request, Message $message): JsonResponse
    {
        abort_unless($message->conversation->hasMember($request->user()->id), 403);
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        $this->messages->setPinned($message, $data['pinned']);

        return response()->json(['message' => $data['pinned'] ? 'Pinned.' : 'Unpinned.']);
    }

    public function forward(Request $request, Message $message): JsonResponse
    {
        $data = $request->validate(['conversation_id' => ['required', 'uuid']]);
        $target = Conversation::findOrFail($data['conversation_id']);
        $new = $this->messages->forward($request->user(), $message, $target);

        return (new MessageResource($new))->response()->setStatusCode(201);
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
