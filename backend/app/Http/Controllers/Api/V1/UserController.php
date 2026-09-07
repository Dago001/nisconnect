<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
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
