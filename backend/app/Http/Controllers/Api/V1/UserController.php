<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('personnelRecord'));
    }

    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:120'],
        ]);
        $request->user()->update($data);

        return new UserResource($request->user()->fresh()->load('personnelRecord'));
    }

    /**
     * Change the sign-in PIN. Requires the current PIN; other devices stay
     * signed in (they can be revoked separately under My Devices).
     */
    public function updatePin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'string'],
            'new_pin' => ['required', 'string', 'min:4', 'max:12', 'regex:/^[0-9]+$/', 'different:current_pin'],
        ]);

        $user = $request->user();
        if (! $user->pin_hash || ! Hash::check($data['current_pin'], $user->pin_hash)) {
            return response()->json([
                'message' => 'Your current PIN is incorrect.',
                'errors' => ['current_pin' => ['Your current PIN is incorrect.']],
            ], 422);
        }

        $user->forceFill(['pin_hash' => Hash::make($data['new_pin'])])->save();
        $this->audit->log('auth.pin_changed', actorId: $user->id, resourceType: 'user', resourceId: $user->id);
        $this->audit->security('pin_changed', userId: $user->id, severity: 'info');

        return response()->json(['message' => 'PIN changed.']);
    }

    public function updatePresence(Request $request): JsonResponse
    {
        $data = $request->validate(['presence' => ['required', 'in:online,offline,away']]);
        $request->user()->update([
            'presence' => $data['presence'],
            'last_seen_at' => now(),
        ]);

        return response()->json(['presence' => $data['presence']]);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        $allowed = array_keys(User::defaultPrivacy());
        $rules = [];
        foreach ($allowed as $key) {
            $rules[$key] = ['sometimes', 'in:everyone,contacts,nobody'];
        }
        $data = $request->validate($rules);

        $privacy = array_merge($request->user()->privacy ?? User::defaultPrivacy(), $data);
        $request->user()->update(['privacy' => $privacy]);

        return response()->json(['privacy' => $privacy]);
    }
}
