<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\User;
use App\Services\Admin\GroupChannelAdminService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Official announcement channels. Publishers post; subscribers read.
 */
class ChannelAdminController extends Controller
{
    public const ROLES = [
        ChannelMember::ROLE_SUBSCRIBER => 'Subscriber',
        ChannelMember::ROLE_PUBLISHER => 'Publisher',
    ];

    public function __construct(
        private readonly GroupChannelAdminService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $query = Channel::query()->withCount([
            'members',
            'members as publishers_count' => fn ($q) => $q->where('role', ChannelMember::ROLE_PUBLISHER),
        ]);
        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('description', 'ilike', $like));
        }
        $channels = $query->orderBy('name')->paginate(25)->withQueryString();

        return view('admin.channels.index', [
            'channels' => $channels,
            'filters' => $request->only(['q']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->detailRules(), $this->detailMessages());
        $channel = $this->service->createChannel($data['name'], $data['description'] ?? null, $request->user());

        $this->audit->log('channel.created', actorId: $request->user()->id, resourceType: 'channel', resourceId: $channel->id,
            metadata: ['via' => 'admin_portal']);

        return redirect()->route('admin.channels.show', $channel)
            ->with('status', 'Channel created. You are its first publisher — add other publishers and subscribers below.');
    }

    public function show(Channel $channel): View
    {
        $members = ChannelMember::where('channel_id', $channel->id)
            ->join('users', 'users.id', '=', 'channel_members.user_id')
            ->leftJoin('personnel_records', 'personnel_records.id', '=', 'users.personnel_record_id')
            ->select('channel_members.*', 'users.display_name', 'users.service_number', 'users.account_state', 'personnel_records.rank')
            ->orderByRaw("CASE channel_members.role WHEN 'publisher' THEN 0 ELSE 1 END")
            ->orderBy('users.display_name')
            ->paginate(25);

        return view('admin.channels.show', [
            'channel' => $channel,
            'members' => $members,
            'roles' => self::ROLES,
            'publishers' => ChannelMember::where('channel_id', $channel->id)->where('role', ChannelMember::ROLE_PUBLISHER)->count(),
            'creator' => $channel->created_by ? User::find($channel->created_by) : null,
        ]);
    }

    public function update(Request $request, Channel $channel): RedirectResponse
    {
        $data = $request->validate($this->detailRules(), $this->detailMessages());
        $this->service->updateChannel($channel, $data['name'], $data['description'] ?? null);

        $this->audit->log('channel.updated', actorId: $request->user()->id, resourceType: 'channel', resourceId: $channel->id,
            metadata: ['changed' => array_keys($channel->getChanges())]);

        return back()->with('status', 'Channel details saved.');
    }

    public function destroy(Request $request, Channel $channel): RedirectResponse
    {
        $this->service->deleteChannel($channel);
        $this->audit->log('channel.deleted', actorId: $request->user()->id, resourceType: 'channel', resourceId: $channel->id,
            metadata: ['name' => $channel->name]);

        return redirect()->route('admin.channels.index')->with('status', "Channel \"{$channel->name}\" deleted.");
    }

    public function addMember(Request $request, Channel $channel): RedirectResponse
    {
        $data = $request->validate([
            'service_number' => ['required', 'string', 'regex:/^[0-9]{1,20}$/'],
            'role' => ['required', Rule::in(array_keys(self::ROLES))],
        ], [
            'service_number.required' => 'Enter the officer\'s Service Number.',
            'service_number.regex' => 'A Service Number may contain digits only.',
        ]);

        $user = User::where('service_number', $data['service_number'])->first();
        if (! $user) {
            return back()->withInput()->withErrors(['service_number' => 'No registered officer has that Service Number.']);
        }
        if (! $user->isActive()) {
            return back()->withInput()->withErrors(['service_number' => 'That officer\'s account isn\'t active, so they can\'t be added.']);
        }

        $added = $this->service->addChannelMember($channel, $user, $data['role']);
        $this->audit->log($added ? 'channel.member_added' : 'channel.member_role_changed', actorId: $request->user()->id,
            resourceType: 'channel', resourceId: $channel->id, metadata: ['member' => $user->id, 'role' => $data['role']]);

        return back()->with('status', $added
            ? "{$user->display_name} added as ".strtolower(self::ROLES[$data['role']]).'.'
            : "{$user->display_name} is now a ".strtolower(self::ROLES[$data['role']]).'.');
    }

    public function removeMember(Request $request, Channel $channel, User $user): RedirectResponse
    {
        if (! $this->service->removeChannelMember($channel, $user)) {
            return back()->with('warning', 'That officer isn\'t a member of this channel.');
        }
        $this->audit->log('channel.member_removed', actorId: $request->user()->id, resourceType: 'channel',
            resourceId: $channel->id, metadata: ['removed_user' => $user->id]);

        return back()->with('status', "{$user->display_name} removed from the channel.");
    }

    /** @return array<string, mixed> */
    private function detailRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    private function detailMessages(): array
    {
        return [
            'name.required' => 'Give the channel a name.',
            'name.max' => 'Keep the name to 120 characters or fewer.',
            'description.max' => 'Keep the description to 1,000 characters or fewer.',
        ];
    }
}
