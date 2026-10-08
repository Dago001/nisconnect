<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\GroupMember;
use App\Models\PersonnelRecord;
use App\Models\Role;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Support\AuditLogger;
use App\Support\CsvExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Registered officer accounts: search, review and account controls. */
class OfficerController extends Controller
{
    public const STATES = ['pending', 'active', 'suspended', 'locked', 'disabled', 'inactive'];

    /** Same window the API login uses for its per-account lockout. */
    private const LOCKOUT_MAX_ATTEMPTS = 5;

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $officers = $this->filtered($request)
            ->with(['personnelRecord', 'roles.role'])
            ->withCount(['devices' => fn ($q) => $q->where('status', Device::STATUS_ACTIVE)])
            ->paginate(25)
            ->withQueryString();

        return view('admin.officers.index', [
            'officers' => $officers,
            'filters' => $request->only(['q', 'state', 'role', 'command', 'directorate', 'sort']),
            'states' => self::STATES,
            'roles' => Role::orderBy('label')->pluck('label', 'name'),
            'commands' => $this->distinctPersonnel('command'),
            'directorates' => $this->distinctPersonnel('directorate'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)
            ->with(['personnelRecord', 'roles.role'])
            ->withCount(['devices' => fn ($q) => $q->where('status', Device::STATUS_ACTIVE)]);

        $this->audit->log('officers.exported', actorId: $request->user()->id, resourceType: 'user',
            metadata: ['filters' => array_filter($request->only(['q', 'state', 'role', 'command', 'directorate']))]);

        // Phone numbers are deliberately left out of exports.
        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $u) {
                $p = $u->personnelRecord;
                yield [
                    $u->service_number,
                    $u->display_name,
                    $p?->rank,
                    $p?->command,
                    $p?->directorate,
                    $u->account_state,
                    $u->roles->map(fn ($r) => $r->role?->label)->filter()->implode('; '),
                    $u->devices_count,
                    $u->last_seen_at?->toDateTimeString(),
                    $u->created_at?->toDateTimeString(),
                ];
            }
        })();

        return CsvExport::download('nisconnect-officers-'.now()->format('Ymd-His').'.csv',
            ['Service Number', 'Name', 'Rank', 'Command', 'Directorate', 'Account state', 'Roles', 'Active devices', 'Last seen', 'Joined'],
            $rows);
    }

    public function show(Request $request, User $user): View
    {
        $user->load(['personnelRecord', 'roles.role', 'devices' => fn ($q) => $q->orderByDesc('last_active_at')]);

        $audit = AuditLog::where('resource_type', 'user')->where('resource_id', $user->id)
            ->orderByDesc('created_at')->limit(15)->get();
        $actors = User::whereIn('id', $audit->pluck('actor_id')->filter()->unique())->pluck('display_name', 'id');
        $security = SecurityEvent::where('user_id', $user->id)->orderByDesc('created_at')->limit(15)->get();

        $counts = [
            'messages' => $user->sentMessages()->count(),
            'groups' => GroupMember::where('user_id', $user->id)->count(),
            'devices' => $user->devices->where('status', Device::STATUS_ACTIVE)->count(),
        ];
        $lockedOut = RateLimiter::tooManyAttempts($this->lockKey($user), self::LOCKOUT_MAX_ATTEMPTS);
        $actor = $request->user();
        $canAct = $actor->hasPermission('officers.manage') && $this->guard($actor, $user, allowSelf: true) === null;
        $isSelf = $actor->id === $user->id;

        return view('admin.officers.show', compact('user', 'audit', 'actors', 'security', 'counts', 'lockedOut', 'canAct', 'isSelf'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($error = $this->guard($request->user(), $user, allowSelf: true)) {
            return back()->with('error', $error);
        }
        $data = $request->validate(['display_name' => ['required', 'string', 'min:2', 'max:80']]);

        $old = $user->display_name;
        $user->update(['display_name' => trim($data['display_name'])]);
        $this->audit->log('officer.updated', actorId: $request->user()->id, resourceType: 'user', resourceId: $user->id,
            metadata: ['field' => 'display_name', 'from' => $old, 'to' => $user->display_name]);

        return back()->with('status', 'Display name updated.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        return $this->changeState($request, $user, User::STATE_SUSPENDED, 'officer.suspended', 'account_suspended', 'Account suspended. The officer has been signed out of every device.');
    }

    public function disable(Request $request, User $user): RedirectResponse
    {
        return $this->changeState($request, $user, User::STATE_DISABLED, 'officer.disabled', 'account_disabled', 'Account disabled. The officer can no longer sign in.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        if ($error = $this->guard($request->user(), $user)) {
            return back()->with('error', $error);
        }
        if ($user->account_state === User::STATE_ACTIVE) {
            return back()->with('warning', 'This account is already active.');
        }

        $from = $user->account_state;
        $user->update(['account_state' => User::STATE_ACTIVE]);
        RateLimiter::clear($this->lockKey($user));
        $this->audit->log('officer.reactivated', actorId: $request->user()->id, resourceType: 'user', resourceId: $user->id,
            metadata: ['from' => $from]);
        $this->audit->security('account_reactivated', userId: $user->id, metadata: ['by' => $request->user()->id]);

        return back()->with('status', 'Account reactivated. The officer can sign in again.');
    }

    public function unlock(Request $request, User $user): RedirectResponse
    {
        if ($error = $this->guard($request->user(), $user, allowSelf: true)) {
            return back()->with('error', $error);
        }

        RateLimiter::clear($this->lockKey($user));
        $wasLocked = $user->account_state === User::STATE_LOCKED;
        if ($wasLocked) {
            $user->update(['account_state' => User::STATE_ACTIVE]);
        }
        $this->audit->log('officer.unlocked', actorId: $request->user()->id, resourceType: 'user', resourceId: $user->id);
        $this->audit->security('account_unlocked', userId: $user->id, metadata: ['by' => $request->user()->id]);

        return back()->with('status', 'Sign-in lockout cleared. The officer can try again now.');
    }

    public function signOutEverywhere(Request $request, User $user): RedirectResponse
    {
        if ($error = $this->guard($request->user(), $user, allowSelf: true)) {
            return back()->with('error', $error);
        }

        $tokens = $user->tokens()->delete();
        $devices = $user->devices()->where('status', Device::STATUS_ACTIVE)->update(['status' => Device::STATUS_REVOKED]);
        $this->audit->log('officer.signed_out_everywhere', actorId: $request->user()->id, resourceType: 'user', resourceId: $user->id,
            metadata: ['tokens' => $tokens, 'devices' => $devices]);
        $this->audit->security('signed_out_everywhere', userId: $user->id, severity: 'warning', metadata: ['by' => $request->user()->id]);

        return back()->with('status', 'The officer has been signed out of every device.');
    }

    private function changeState(Request $request, User $user, string $state, string $action, string $event, string $message): RedirectResponse
    {
        if ($error = $this->guard($request->user(), $user)) {
            return back()->with('error', $error);
        }
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $from = $user->account_state;
        $user->update(['account_state' => $state, 'presence' => 'offline']);
        $tokens = $user->tokens()->delete();
        $this->audit->log($action, actorId: $request->user()->id, resourceType: 'user', resourceId: $user->id,
            metadata: ['from' => $from, 'reason' => $data['reason'], 'tokens_revoked' => $tokens]);
        $this->audit->security($event, userId: $user->id, severity: 'warning',
            metadata: ['by' => $request->user()->id, 'reason' => $data['reason']]);

        return back()->with('status', $message);
    }

    /**
     * Platform lock-out protection. Returns a message when the action is not allowed.
     */
    private function guard(User $actor, User $target, bool $allowSelf = false): ?string
    {
        if (! $allowSelf && $actor->id === $target->id) {
            return 'You cannot do this to your own account.';
        }
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return 'Only a super administrator can change a super administrator\'s account.';
        }

        return null;
    }

    private function lockKey(User $user): string
    {
        return 'login-account:'.$user->service_number;
    }

    private function filtered(Request $request): Builder
    {
        $query = User::query();

        if ($term = trim((string) $request->query('q', ''))) {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('service_number', 'like', $like)
                    ->orWhere('display_name', 'ilike', $like)
                    ->orWhereHas('personnelRecord', fn ($p) => $p->where('surname', 'ilike', $like)
                        ->orWhere('first_name', 'ilike', $like)
                        ->orWhere('other_name', 'ilike', $like));
            });
        }
        if (in_array($state = $request->query('state'), self::STATES, true)) {
            $query->where('account_state', $state);
        }
        if (is_string($role = $request->query('role')) && $role !== '') {
            $query->whereHas('roles.role', fn ($q) => $q->where('name', $role));
        }
        foreach (['command', 'directorate'] as $field) {
            if (is_string($value = $request->query($field)) && $value !== '') {
                $query->whereHas('personnelRecord', fn ($q) => $q->where($field, $value));
            }
        }

        return $request->query('sort') === 'name'
            ? $query->orderBy('display_name')->orderBy('id')
            : $query->orderByDesc('created_at')->orderBy('id');
    }

    /** @return list<string> */
    private function distinctPersonnel(string $column): array
    {
        return PersonnelRecord::whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column)->all();
    }
}
