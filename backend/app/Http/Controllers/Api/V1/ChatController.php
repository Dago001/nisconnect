<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\StartChatRequest;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Messaging\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatController extends Controller
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ConversationResource::collection(
            $this->conversations->forUser($request->user())
        );
    }

    public function show(Request $request, Conversation $conversation): ConversationResource
    {
        abort_unless($conversation->hasMember($request->user()->id), 403);

        return new ConversationResource(
            $conversation->load('members.user.personnelRecord')
        );
    }

    /**
     * Start (or resume) a direct chat with another officer by Service Number.
     */
    public function start(StartChatRequest $request): JsonResponse
    {
        $target = User::where('service_number', $request->validated('service_number'))
            ->where('account_state', User::STATE_ACTIVE)
            ->first();

        if (! $target || $target->id === $request->user()->id) {
            return response()->json(['message' => 'Officer not found.'], 404);
        }

        $conversation = $this->conversations->directConversation($request->user(), $target);

        return (new ConversationResource($conversation->load('members.user.personnelRecord')))
            ->response()
            ->setStatusCode(201);
    }
}
