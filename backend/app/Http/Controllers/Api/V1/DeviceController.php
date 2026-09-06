<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Models\PushToken;
use App\Services\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $currentDeviceId = $request->user()->currentAccessToken()->device_id ?? null;

        $devices = $request->user()->devices()
            ->orderByDesc('last_active_at')
            ->get()
            ->each(fn (Device $d) => $d->current = ($d->id === $currentDeviceId));

        return DeviceResource::collection($devices);
    }

    /**
     * Register (or refresh) the FCM/APNs push token for the current device.
     */
    public function registerPushToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'in:fcm,apns'],
            'token' => ['required', 'string', 'max:512'],
        ]);

        $deviceId = $request->user()->currentAccessToken()->device_id ?? null;
        abort_if($deviceId === null, 422, 'No device is bound to this session.');

        PushToken::updateOrCreate(
            ['provider' => $data['provider'], 'token' => $data['token']],
            ['user_id' => $request->user()->id, 'device_id' => $deviceId],
        );
        Device::where('id', $deviceId)->update(['push_token' => $data['token']]);

        return response()->json(['message' => 'Push token registered.']);
    }

    public function destroy(Request $request, Device $device): JsonResponse
    {
        abort_unless($device->user_id === $request->user()->id, 403);

        // Revoke the device and any tokens bound to it.
        $device->update(['status' => Device::STATUS_REVOKED]);
        $request->user()->tokens()->where('device_id', $device->id)->delete();

        $this->audit->log('device.removed', actorId: $request->user()->id,
            resourceType: 'device', resourceId: $device->id);
        $this->audit->security('device_removed', userId: $request->user()->id,
            severity: 'warning', deviceId: $device->id);

        return response()->json(['message' => 'Device removed.']);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $currentDeviceId = $request->user()->currentAccessToken()->device_id ?? null;

        $request->user()->devices()->where('id', '!=', $currentDeviceId)
            ->update(['status' => Device::STATUS_REVOKED]);
        $request->user()->tokens()->where('device_id', '!=', $currentDeviceId)->delete();

        $this->audit->log('device.sign_out_all', actorId: $request->user()->id, resourceType: 'user');

        return response()->json(['message' => 'All other devices signed out.']);
    }
}
