<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Conversation;
use App\Models\Group;
use App\Models\User;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Administration of official organisational groups and announcement channels.
 */
class OrgAdminController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $channels = Channel::withCount('members')->orderBy('name')->get();
        $groups = Group::where('type', Group::TYPE_ORGANISATIONAL)->orderBy('name')->get();

        return view('admin.org', compact('channels', 'groups'));
    }

    public function storeChannel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $channel = Channel::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'created_by' => $request->user()->id,
        ]);
        // Creator becomes the first publisher.
        ChannelMember::create([
            'channel_id' => $channel->id, 'user_id' => $request->user()->id, 'role' => ChannelMember::ROLE_PUBLISHER,
        ]);
        $this->audit->log('channel.created', actorId: $request->user()->id,
            resourceType: 'channel', resourceId: $channel->id);

        return back()->with('status', 'Channel created.');
    }

    public function addPublisher(Request $request, Channel $channel): RedirectResponse
    {
        $data = $request->validate(['service_number' => ['required', 'string']]);
        $user = User::where('service_number', $data['service_number'])->first();
        if (! $user) {
            return back()->withErrors(['service_number' => 'Officer not found.']);
        }
        ChannelMember::updateOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $user->id],
            ['role' => ChannelMember::ROLE_PUBLISHER],
        );
        $this->audit->log('channel.publisher_added', actorId: $request->user()->id,
            resourceType: 'channel', resourceId: $channel->id, metadata: ['publisher' => $user->id]);

        return back()->with('status', 'Publisher added.');
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $data) {
            $conversation = Conversation::create([
                'type' => Conversation::TYPE_GROUP,
                'title' => $data['name'],
                'created_by' => $request->user()->id,
            ]);
            $group = Group::create([
                'conversation_id' => $conversation->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => Group::TYPE_ORGANISATIONAL,
                'created_by' => $request->user()->id,
            ]);
            $conversation->update(['group_id' => $group->id]);
            $this->audit->log('group.created', actorId: $request->user()->id,
                resourceType: 'group', resourceId: $group->id, metadata: ['type' => 'organisational']);
        });

        return back()->with('status', 'Organisational group created.');
    }
}
