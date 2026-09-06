<?php

namespace App\Services\Calls;

use App\Events\CallSignal;
use App\Models\BlockedUser;
use App\Models\Call;
use App\Models\CallParticipant;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Signalling authority for voice/video calls. Authorises the caller, records
 * the call and participants, and mints per-participant LiveKit tokens. Media
 * flows through the SFU, never through this service.
 */
class CallService
{
    public function __construct(
        private readonly LiveKitTokenService $tokens,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Start a call in a conversation (direct or group).
     *
     * @return array{call: Call, token: string}
     */
    public function initiate(User $caller, Conversation $conversation, string $type): array
    {
        if (! $conversation->hasMember($caller->id)) {
            throw ValidationException::withMessages(['conversation' => 'You are not a member of this conversation.']);
        }

        $others = $conversation->members()->where('user_id', '!=', $caller->id)->pluck('user_id');

        if ($conversation->type === Conversation::TYPE_DIRECT) {
            $other = $others->first();
            if ($other && $this->isBlockedBetween($caller->id, $other)) {
                throw ValidationException::withMessages(['conversation' => 'You cannot call this officer.']);
            }
        }

        $mode = $conversation->type === Conversation::TYPE_DIRECT ? Call::MODE_DIRECT : Call::MODE_GROUP;

        $call = DB::transaction(function () use ($caller, $conversation, $type, $mode, $others) {
            $call = Call::create([
                'conversation_id' => $conversation->id,
                'type' => $type,
                'mode' => $mode,
                'initiator_id' => $caller->id,
                'room_name' => 'call_'.Str::uuid()->toString(),
                'status' => 'ringing',
                'started_at' => Carbon::now(),
            ]);

            CallParticipant::create([
                'call_id' => $call->id, 'user_id' => $caller->id,
                'state' => 'joined', 'joined_at' => Carbon::now(),
            ]);
            foreach ($others as $uid) {
                CallParticipant::create(['call_id' => $call->id, 'user_id' => $uid, 'state' => 'ringing']);
            }

            return $call;
        });

        // Notify callees over their personal channels.
        foreach ($others as $uid) {
            broadcast(new CallSignal($uid, 'call.incoming', [
                'call_id' => $call->id,
                'type' => $call->type,
                'mode' => $call->mode,
                'room' => $call->room_name,
                'from' => $caller->display_name,
                'from_id' => $caller->id,
            ]));
        }

        $this->audit->log('call.initiated', actorId: $caller->id, resourceType: 'call', resourceId: $call->id,
            metadata: ['type' => $type, 'mode' => $mode]);

        return ['call' => $call, 'token' => $this->tokenFor($call, $caller)];
    }

    /**
     * Mint a join token for a participant who is authorised on the call.
     */
    public function tokenFor(Call $call, User $user): string
    {
        $participant = CallParticipant::where('call_id', $call->id)->where('user_id', $user->id)->first();
        abort_if($participant === null, 403, 'You are not a participant of this call.');

        return $this->tokens->mint(
            room: $call->room_name,
            identity: $user->id,
            name: $user->display_name,
        );
    }

    public function answer(Call $call, User $user): array
    {
        $this->transitionParticipant($call, $user, 'joined', joined: true);
        if ($call->status === 'ringing') {
            $call->update(['status' => 'connected']);
        }
        $this->broadcastToOthers($call, $user, 'call.answered');
        $this->audit->log('call.answered', actorId: $user->id, resourceType: 'call', resourceId: $call->id);

        return ['call' => $call->fresh(), 'token' => $this->tokenFor($call, $user)];
    }

    public function decline(Call $call, User $user): void
    {
        $this->transitionParticipant($call, $user, 'declined');
        $this->broadcastToOthers($call, $user, 'call.declined');

        // If nobody is left ringing/joined besides the initiator on a direct call, end it.
        if ($call->mode === Call::MODE_DIRECT) {
            $call->update(['status' => 'declined', 'ended_at' => Carbon::now()]);
        }
        $this->audit->log('call.declined', actorId: $user->id, resourceType: 'call', resourceId: $call->id);
    }

    public function end(Call $call, User $user): void
    {
        $this->transitionParticipant($call, $user, 'left', left: true);

        $active = CallParticipant::where('call_id', $call->id)
            ->whereIn('state', ['joined', 'ringing'])->count();
        if ($active <= 1 || $call->initiator_id === $user->id) {
            $call->update(['status' => 'ended', 'ended_at' => Carbon::now()]);
            $this->broadcastToOthers($call, $user, 'call.ended');
        }
        $this->audit->log('call.ended', actorId: $user->id, resourceType: 'call', resourceId: $call->id);
    }

    public function history(User $user, int $perPage = 20)
    {
        return Call::query()
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with('participants')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    private function transitionParticipant(Call $call, User $user, string $state, bool $joined = false, bool $left = false): void
    {
        $participant = CallParticipant::where('call_id', $call->id)->where('user_id', $user->id)->first();
        abort_if($participant === null, 403, 'You are not a participant of this call.');

        $participant->update(array_filter([
            'state' => $state,
            'joined_at' => $joined ? Carbon::now() : $participant->joined_at,
            'left_at' => $left ? Carbon::now() : $participant->left_at,
        ], fn ($v) => $v !== null));
    }

    private function broadcastToOthers(Call $call, User $actor, string $event): void
    {
        CallParticipant::where('call_id', $call->id)->where('user_id', '!=', $actor->id)
            ->pluck('user_id')
            ->each(fn ($uid) => broadcast(new CallSignal($uid, $event, [
                'call_id' => $call->id, 'by' => $actor->id,
            ])));
    }

    private function isBlockedBetween(string $a, string $b): bool
    {
        return BlockedUser::where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))
            ->exists();
    }
}
