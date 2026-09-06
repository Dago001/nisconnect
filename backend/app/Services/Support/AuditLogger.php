<?php

namespace App\Services\Support;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Writes append-only audit and security records. Never records message content.
 */
class AuditLogger
{
    public function __construct(private readonly Request $request)
    {
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        ?string $actorId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        string $result = 'success',
        array $metadata = [],
        ?string $deviceId = null,
    ): void {
        AuditLog::create([
            'actor_id' => $actorId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'result' => $result,
            'ip' => $this->request->ip(),
            'device_id' => $deviceId,
            'metadata' => $metadata ?: null,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function security(
        string $event,
        ?string $userId = null,
        string $severity = 'info',
        array $metadata = [],
        ?string $deviceId = null,
    ): void {
        SecurityEvent::create([
            'user_id' => $userId,
            'event' => $event,
            'severity' => $severity,
            'ip' => $this->request->ip(),
            'device_id' => $deviceId,
            'metadata' => $metadata ?: null,
            'created_at' => Carbon::now(),
        ]);
    }
}
