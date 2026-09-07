<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BlockedUser;
use App\Models\Report;
use App\Services\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SafetyController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function blocked(Request $request): JsonResponse
    {
        $blocked = BlockedUser::where('blocker_id', $request->user()->id)
            ->join('users', 'users.id', '=', 'blocked_users.blocked_id')
            ->orderBy('users.display_name')
            ->get(['users.id', 'users.service_number', 'users.display_name']);

        return response()->json(['data' => $blocked]);
    }

    public function block(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'uuid', 'exists:users,id']]);
        abort_if($data['user_id'] === $request->user()->id, 422, 'You cannot block yourself.');

        BlockedUser::firstOrCreate([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $data['user_id'],
        ]);
        $this->audit->log('user.blocked', actorId: $request->user()->id,
            resourceType: 'user', resourceId: $data['user_id']);

        return response()->json(['message' => 'Officer blocked.']);
    }

    public function unblock(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'uuid']]);
        BlockedUser::where('blocker_id', $request->user()->id)
            ->where('blocked_id', $data['user_id'])->delete();

        return response()->json(['message' => 'Officer unblocked.']);
    }

    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:user,message,group'],
            'target_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:200'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'target_type' => $data['target_type'],
            'target_id' => $data['target_id'],
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'open',
        ]);
        $this->audit->log('report.filed', actorId: $request->user()->id,
            resourceType: 'report', resourceId: $report->id,
            metadata: ['target_type' => $data['target_type']]);

        return response()->json(['message' => 'Report submitted. Thank you.', 'id' => $report->id], 201);
    }
}
