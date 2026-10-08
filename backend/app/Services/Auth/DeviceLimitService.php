<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Models\User;
use App\Services\Admin\SettingsService;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Enforces the `max_devices_per_officer` system setting. When a sign-in
 * pushes an officer over the limit, the least recently active other devices
 * are revoked and their access tokens deleted.
 */
class DeviceLimitService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<string> ids of the devices that were revoked
     */
    public function enforce(User $user, Device $current): array
    {
        $limit = max(1, (int) $this->settings->get('max_devices_per_officer'));

        $others = Device::where('user_id', $user->id)
            ->where('status', Device::STATUS_ACTIVE)
            ->where('id', '!=', $current->id)
            ->orderByRaw('last_active_at DESC NULLS LAST')
            ->orderByDesc('created_at')
            ->get();

        // The current device always counts as one of the allowed devices.
        $excess = $others->slice($limit - 1);
        if ($excess->isEmpty()) {
            return [];
        }

        $ids = $excess->pluck('id')->values()->all();
        Device::whereIn('id', $ids)->update(['status' => Device::STATUS_REVOKED]);
        DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->whereIn('device_id', $ids)
            ->delete();

        $this->audit->security('device_limit_revoked', userId: $user->id, severity: 'info',
            metadata: ['limit' => $limit, 'revoked_device_ids' => $ids], deviceId: $current->id);

        return $ids;
    }
}
