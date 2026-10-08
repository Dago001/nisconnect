<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'avatar_url' => $this->avatar_path,
            'group_id' => $this->group_id,
            'channel_id' => $this->channel_id,
            'last_message_id' => $this->last_message_id,
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($m) => [
                'user_id' => $m->user_id,
                'role' => $m->role,
                'display_name' => $m->user?->display_name,
                'service_number' => $m->user?->service_number,
                'rank' => $m->user?->personnelRecord?->rank,
            ])),
            'last_message' => $this->whenLoaded('lastMessage', fn () => $this->lastMessage ? [
                'id' => $this->lastMessage->id,
                'sender_id' => $this->lastMessage->sender_id,
                'type' => $this->lastMessage->type,
                // Deleted messages keep their row but must not leak content.
                'body' => $this->lastMessage->status === 'deleted' ? null : $this->lastMessage->body,
                'status' => $this->lastMessage->status,
                'created_at' => $this->lastMessage->created_at,
            ] : null),
            'updated_at' => $this->updated_at,
        ];
    }
}
