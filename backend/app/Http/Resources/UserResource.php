<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_number' => $this->service_number,   // always numeric string
            'display_name' => $this->display_name,
            'avatar_url' => $this->avatar_path,
            'phone' => $this->phone,
            'account_state' => $this->account_state,
            'presence' => $this->presence,
            'privacy' => $this->privacy,
            'rank' => $this->whenLoaded('personnelRecord', fn () => $this->personnelRecord?->rank),
            'directorate' => $this->whenLoaded('personnelRecord', fn () => $this->personnelRecord?->directorate),
            'department' => $this->whenLoaded('personnelRecord', fn () => $this->personnelRecord?->department),
            'command' => $this->whenLoaded('personnelRecord', fn () => $this->personnelRecord?->command),
            'created_at' => $this->created_at,
        ];
    }
}
