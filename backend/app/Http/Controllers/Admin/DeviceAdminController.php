<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

/** Every registered device across all officers. */
class DeviceAdminController extends Controller
{
    public const PLATFORMS = ['android', 'ios', 'web'];

    public const STATUSES = [Device::STATUS_ACTIVE, Device::STATUS_REVOKED];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $query = Device::query()->with('user.personnelRecord');

        if ($term = trim((string) $request->query('q', ''))) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('user', fn ($u) => $u->where('service_number', 'like', $like)
                ->orWhere('display_name', 'ilike', $like));
        }
        if (in_array($platform = $request->query('platform'), self::PLATFORMS, true)) {
            $query->where('platform', $platform);
        }
        if (in_array($status = $request->query('status'), self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $devices = $query->orderByRaw('last_active_at DESC NULLS LAST')->orderBy('id')
            ->paginate(25)->withQueryString();

        return view('admin.devices.index', [
            'devices' => $devices,
            'filters' => $request->only(['q', 'platform', 'status']),
            'platforms' => self::PLATFORMS,
            'statuses' => self::STATUSES,
            'counts' => [
                'active' => Device::where('status', Device::STATUS_ACTIVE)->count(),
                'revoked' => Device::where('status', Device::STATUS_REVOKED)->count(),
            ],
        ]);
    }

    public function revoke(Request $request, Device $device): RedirectResponse
    {
        $actor = $request->user();
        $owner = $device->user;
        if ($owner && $owner->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return back()->with('error', 'Only a super administrator can revoke a super administrator\'s device.');
        }

        $device->update(['status' => Device::STATUS_REVOKED]);
        $tokens = PersonalAccessToken::where('device_id', $device->id)->delete();

        $this->audit->log('device.revoked', actorId: $actor->id, resourceType: 'device', resourceId: $device->id,
            metadata: ['user_id' => $device->user_id, 'tokens_revoked' => $tokens], deviceId: $device->id);
        $this->audit->security('device_revoked', userId: $device->user_id, severity: 'warning',
            metadata: ['by' => $actor->id], deviceId: $device->id);

        return back()->with('status', "“{$device->name}” has been revoked and signed out.");
    }
}
