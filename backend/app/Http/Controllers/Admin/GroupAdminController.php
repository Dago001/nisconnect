<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\Admin\GroupChannelAdminService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GroupAdminController extends Controller
{
    public const ROLES = [
        GroupMember::ROLE_MEMBER => 'Member',
        GroupMember::ROLE_MODERATOR => 'Moderator',
        GroupMember::ROLE_ADMIN => 'Admin',
        GroupMember::ROLE_OWNER => 'Owner',
    ];

    public const TYPES = [Group::TYPE_STANDARD => 'Officer-created', Group::TYPE_ORGANISATIONAL => 'Organisational'];

    public function __construct(
        private readonly GroupChannelAdminService $service,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $query = Group::query()->withCount('members');
        if ($q = trim((string) $request->query('q'))) {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('description', 'ilike', $like));
        }
        if (array_key_exists((string) $request->query('type'), self::TYPES)) {
            $query->where('type', $request->query('type'));
        }
        $groups = $query->orderBy('name')->paginate(25)->withQueryString();
        $creators = User::whereIn('id', $groups->pluck('created_by')->filter()->unique())->pluck('display_name', 'id');

        return view('admin.groups.index', [
            'groups' => $groups,
            'creators' => $creators,
            'types' => self::TYPES,
            'filters' => $request->only(['q', 'type']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->detailRules(), $this->detailMessages());
        $group = $this->service->createOrganisationalGroup($data['name'], $data['description'] ?? null, $request->user());

        $this->audit->log('group.created', actorId: $request->user()->id, resourceType: 'group', resourceId: $group->id,
            metadata: ['type' => Group::TYPE_ORGANISATIONAL, 'via' => 'admin_portal']);

        return redirect()->route('admin.groups.show', $group)->with('status', 'Organisational group created. Add its members below.');
    }

    public function show(Group $group): View
    {
        $members = $group->members()->with('user.personnelRecord')
            ->join('users', 'users.id', '=', 'group_members.user_id')
            ->select('group_members.*')
            ->orderByRaw("CASE group_members.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 WHEN 'moderator' THEN 2 ELSE 3 END")
            ->orderBy('users.display_name')
            ->paginate(25);

        return view('admin.groups.show', [
            'group' => $group,
            'members' => $members,
            'roles' => self::ROLES,
            'types' => self::TYPES,
            'creator' => $group->created_by ? User::find($group->created_by) : null,
        ]);
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $data = $request->validate($this->detailRules(), $this->detailMessages());
        $this->service->updateGroup($group, $data['name'], $data['description'] ?? null);

        $this->audit->log('group.updated', actorId: $request->user()->id, resourceType: 'group', resourceId: $group->id,
            metadata: ['changed' => array_keys($group->getChanges())]);

        return back()->with('status', 'Group details saved.');
    }

    public function destroy(Request $request, Group $group): RedirectResponse
    {
        $this->service->deleteGroup($group);
        $this->audit->log('group.deleted', actorId: $request->user()->id, resourceType: 'group', resourceId: $group->id,
            metadata: ['name' => $group->name, 'type' => $group->type]);

        return redirect()->route('admin.groups.index')->with('status', "Group \"{$group->name}\" deleted.");
    }

    public function addMember(Request $request, Group $group): RedirectResponse
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

        $added = $this->service->addGroupMember($group, $user, $data['role'], $request->user());
        $this->audit->log($added ? 'group.member_added' : 'group.member_role_changed', actorId: $request->user()->id,
            resourceType: 'group', resourceId: $group->id, metadata: ['member' => $user->id, 'role' => $data['role']]);

        return back()->with('status', $added
            ? "{$user->display_name} added to the group as ".strtolower(self::ROLES[$data['role']]).'.'
            : "{$user->display_name} is now ".strtolower(self::ROLES[$data['role']]).' of this group.');
    }

    public function removeMember(Request $request, Group $group, User $user): RedirectResponse
    {
        if (! $this->service->removeGroupMember($group, $user)) {
            return back()->with('warning', 'That officer isn\'t a member of this group.');
        }
        $this->audit->log('group.member_removed', actorId: $request->user()->id, resourceType: 'group',
            resourceId: $group->id, metadata: ['removed_user' => $user->id]);

        return back()->with('status', "{$user->display_name} removed from the group.");
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
            'name.required' => 'Give the group a name.',
            'name.max' => 'Keep the name to 120 characters or fewer.',
            'description.max' => 'Keep the description to 1,000 characters or fewer.',
        ];
    }
}
