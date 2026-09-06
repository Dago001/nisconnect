<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messaging\CreateGroupRequest;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $groups = Group::whereHas('members', fn ($q) => $q->where('user_id', $request->user()->id))
            ->withCount('members')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'avatar_path', 'type', 'conversation_id']);

        return response()->json(['data' => $groups]);
    }

    public function store(CreateGroupRequest $request): JsonResponse
    {
        $user = $request->user();

        $group = DB::transaction(function () use ($request, $user) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'title' => $request->validated('name'),
                'created_by' => $user->id,
            ]);

            $group = Group::create([
                'conversation_id' => $conversation->id,
                'name' => $request->validated('name'),
                'description' => $request->validated('description'),
                'type' => Group::TYPE_STANDARD,
                'created_by' => $user->id,
            ]);
            $conversation->update(['group_id' => $group->id]);

            // Creator is owner.
            $this->addMember($group, $conversation, $user->id, GroupMember::ROLE_OWNER, $user->id);

            // Add validated members that are real active users.
            $memberIds = collect($request->validated('members') ?? [])
                ->filter(fn ($id) => $id !== $user->id)
                ->unique();
            $valid = User::whereIn('id', $memberIds)->where('account_state', User::STATE_ACTIVE)->pluck('id');
            foreach ($valid as $id) {
                $this->addMember($group, $conversation, $id, GroupMember::ROLE_MEMBER, $user->id);
            }

            return $group;
        });

        $this->audit->log('group.created', actorId: $user->id, resourceType: 'group', resourceId: $group->id);

        return response()->json([
            'data' => $group->load('members')->only(['id', 'name', 'description', 'type', 'conversation_id']),
        ], 201);
    }

    public function addMembers(Request $request, Group $group): JsonResponse
    {
        $this->authorizeAdmin($request->user()->id, $group);
        $data = $request->validate([
            'members' => ['required', 'array', 'min:1', 'max:200'],
            'members.*' => ['uuid'],
        ]);

        $conversation = $group->conversation;
        $valid = User::whereIn('id', $data['members'])->where('account_state', User::STATE_ACTIVE)->pluck('id');
        foreach ($valid as $id) {
            $this->addMember($group, $conversation, $id, GroupMember::ROLE_MEMBER, $request->user()->id);
        }

        $this->audit->log('group.members_added', actorId: $request->user()->id,
            resourceType: 'group', resourceId: $group->id, metadata: ['count' => $valid->count()]);

        return response()->json(['message' => 'Members added.', 'added' => $valid->count()]);
    }

    public function removeMember(Request $request, Group $group, string $userId): JsonResponse
    {
        $this->authorizeAdmin($request->user()->id, $group);

        GroupMember::where('group_id', $group->id)->where('user_id', $userId)->delete();
        $group->conversation->members()->where('user_id', $userId)
            ->update(['left_at' => Carbon::now()]);

        $this->audit->log('group.member_removed', actorId: $request->user()->id,
            resourceType: 'group', resourceId: $group->id, metadata: ['removed_user' => $userId]);

        return response()->json(['message' => 'Member removed.']);
    }

    public function leave(Request $request, Group $group): JsonResponse
    {
        GroupMember::where('group_id', $group->id)->where('user_id', $request->user()->id)->delete();
        $group->conversation->members()->where('user_id', $request->user()->id)
            ->update(['left_at' => Carbon::now()]);

        return response()->json(['message' => 'You left the group.']);
    }

    private function addMember(Group $group, Conversation $conversation, string $userId, string $role, string $addedBy): void
    {
        GroupMember::firstOrCreate(
            ['group_id' => $group->id, 'user_id' => $userId],
            ['role' => $role, 'added_by' => $addedBy],
        );
        $conversation->members()->updateOrCreate(
            ['user_id' => $userId],
            ['role' => $role === GroupMember::ROLE_OWNER ? 'owner' : 'member', 'joined_at' => Carbon::now(), 'left_at' => null],
        );
    }

    private function authorizeAdmin(string $userId, Group $group): void
    {
        $member = GroupMember::where('group_id', $group->id)->where('user_id', $userId)->first();
        abort_unless($member && in_array($member->role, [GroupMember::ROLE_OWNER, GroupMember::ROLE_ADMIN], true), 403,
            'Only group admins can perform this action.');
    }
}
