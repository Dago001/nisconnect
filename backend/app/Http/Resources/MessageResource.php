<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'type' => $this->type,
            'body' => $this->body,
            'reply_to_id' => $this->reply_to_id,
            'status' => $this->status,
            'edited_at' => $this->edited_at,
            'pinned_at' => $this->pinned_at,
            'attachments' => $this->whenLoaded('attachments'),
            'created_at' => $this->created_at,
        ];
    }
}
