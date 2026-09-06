<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'platform' => $this->platform,
            'model' => $this->model,
            'os_version' => $this->os_version,
            'app_version' => $this->app_version,
            'last_active_at' => $this->last_active_at,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'current' => $this->when(isset($this->current), fn () => (bool) $this->current),
        ];
    }
}
