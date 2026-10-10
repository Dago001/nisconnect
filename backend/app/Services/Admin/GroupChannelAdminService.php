<?php

namespace App\Services\Admin;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Administrative management of groups and official channels. Mirrors the
 * membership rules of the officer API: a group member is also a member of the
 * group's conversation; removal marks the conversation membership as left.
 */
class GroupChannelAdminService
{
    public function createOrganisationalGroup(string $name, ?string $description, User $actor): Group
    {
        return DB::transaction(function () use ($name, $description, $actor) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'title' => $name,
                'created_by' => $actor->id,
            ]);
            $group = Group::create([
                'conversation_id' => $conversation->id,
                'name' => $name,
                'description' => $description,
                'type' => Group::TYPE_ORGANISATIONAL,
                'created_by' => $actor->id,
            ]);
            $conversation->update(['group_id' => $group->id]);

            return $group;
        });
    }

    public function updateGroup(Group $group, string $name, ?string $description): void
    {
        DB::transaction(function () use ($group, $name, $description) {
            $group->update(['name' => $name, 'description' => $description]);
            $group->conversation?->update(['title' => $name]);
        });
    }

    /** Adds (or changes the role of) a group member. Returns true when newly added. */
    public function addGroupMember(Group $group, User $user, string $role, User $actor): bool
    {
        return DB::transaction(function () use ($group, $user, $role, $actor) {
            $member = GroupMember::firstOrNew(['group_id' => $group->id, 'user_id' => $user->id]);
            $isNew = ! $member->exists;
            $member->fill(['role' => $role, 'added_by' => $member->added_by ?? $actor->id])->save();

            $conversationRole = match ($role) {
                GroupMember::ROLE_OWNER => 'owner',
                GroupMember::ROLE_ADMIN => 'admin',
                default => 'member',
            };
            if ($group->conversation) {
                $existing = $group->conversation->members()->where('user_id', $user->id)->first();
                $group->conversation->members()->updateOrCreate(
                    ['user_id' => $user->id],
                    ['role' => $conversationRole, 'left_at' => null]
                        + ($existing && $existing->left_at === null ? [] : ['joined_at' => Carbon::now()]),
                );
            }

            return $isNew;
        });
    }

    public function removeGroupMember(Group $group, User $user): bool
    {
        return DB::transaction(function () use ($group, $user) {
            $deleted = GroupMember::where('group_id', $group->id)->where('user_id', $user->id)->delete();
            $group->conversation?->members()->where('user_id', $user->id)->whereNull('left_at')
                ->update(['left_at' => Carbon::now()]);

            return $deleted > 0;
        });
    }

    /**
     * Soft-deletes the group and closes its conversation for every member, so
     * it disappears from officers' chat lists while history stays on record.
     */
    public function deleteGroup(Group $group): void
    {
        DB::transaction(function () use ($group) {
            $group->conversation?->members()->whereNull('left_at')->update(['left_at' => Carbon::now()]);
            $group->delete();
        });
    }

    public function createChannel(string $name, ?string $description, User $actor): Channel
    {
        return DB::transaction(function () use ($name, $description, $actor) {
            $channel = Channel::create(['name' => $name, 'description' => $description, 'created_by' => $actor->id]);
            // The creating administrator becomes the first publisher.
            ChannelMember::create(['channel_id' => $channel->id, 'user_id' => $actor->id, 'role' => ChannelMember::ROLE_PUBLISHER]);

            return $channel;
        });
    }

    public function updateChannel(Channel $channel, string $name, ?string $description): void
    {
        DB::transaction(function () use ($channel, $name, $description) {
            $channel->update(['name' => $name, 'description' => $description]);
            Conversation::where('channel_id', $channel->id)->update(['title' => $name]);
        });
    }

    /** Adds (or changes the role of) a channel member. Returns true when newly added. */
    public function addChannelMember(Channel $channel, User $user, string $role): bool
    {
        $member = ChannelMember::updateOrCreate(['channel_id' => $channel->id, 'user_id' => $user->id], ['role' => $role]);

        return $member->wasRecentlyCreated;
    }

    public function removeChannelMember(Channel $channel, User $user): bool
    {
        return ChannelMember::where('channel_id', $channel->id)->where('user_id', $user->id)->delete() > 0;
    }

    public function deleteChannel(Channel $channel): void
    {
        $channel->delete(); // soft delete; posts stay on record
    }
}
