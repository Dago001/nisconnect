<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Conversation;
use App\Services\Calls\CallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(private readonly CallService $calls) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'conversation_id' => ['required', 'uuid'],
            'type' => ['required', 'in:voice,video'],
        ]);

        $conversation = Conversation::findOrFail($data['conversation_id']);
        $result = $this->calls->initiate($request->user(), $conversation, $data['type']);

        return response()->json($this->present($result['call'], $result['token']), 201);
    }

    public function token(Request $request, Call $call): JsonResponse
    {
        return response()->json([
            'call_id' => $call->id,
            'room' => $call->room_name,
            'token' => $this->calls->tokenFor($call, $request->user()),
            'livekit_url' => config('services.livekit.host'),
        ]);
    }

    public function answer(Request $request, Call $call): JsonResponse
    {
        $result = $this->calls->answer($call, $request->user());

        return response()->json($this->present($result['call'], $result['token']));
    }

    public function decline(Request $request, Call $call): JsonResponse
    {
        $this->calls->decline($call, $request->user());

        return response()->json(['message' => 'Call declined.']);
    }

    public function end(Request $request, Call $call): JsonResponse
    {
        $this->calls->end($call, $request->user());

        return response()->json(['message' => 'Call ended.']);
    }

    public function history(Request $request): JsonResponse
    {
        $page = $this->calls->history($request->user());

        return response()->json([
            'data' => collect($page->items())->map(fn (Call $c) => [
                'id' => $c->id,
                'type' => $c->type,
                'mode' => $c->mode,
                'status' => $c->status,
                'initiator_id' => $c->initiator_id,
                'started_at' => $c->started_at,
                'ended_at' => $c->ended_at,
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    private function present(Call $call, string $token): array
    {
        return [
            'call' => [
                'id' => $call->id,
                'room' => $call->room_name,
                'type' => $call->type,
                'mode' => $call->mode,
                'status' => $call->status,
            ],
            'token' => $token,
            'livekit_url' => config('services.livekit.host'),
        ];
    }
}
