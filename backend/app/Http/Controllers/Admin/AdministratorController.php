<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Directorate;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Admin\SettingsService;
use App\Services\Support\AuditLogger;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Appoint and remove administrators; reset their credentials. */
class AdministratorController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $admins = User::whereHas('roles.role', fn ($q) => $q->whereIn('name', AdminPermissions::ADMIN_ROLES))
            ->with(['personnelRecord', 'roles' => fn ($q) => $q->with('role')->orderBy('created_at')])
            ->orderBy('display_name')
            ->get();

        $me = $request->user();
        $roles = Role::whereIn('name', AdminPermissions::ADMIN_ROLES)->get()
            ->sortBy(fn ($r) => array_search($r->name, AdminPermissions::ADMIN_ROLES, true))->values();
        $assignable = $roles->filter(fn ($r) => $r->name !== 'super_admin' || $me->isSuperAdmin())->values();
        $directorates = Directorate::orderBy('name')->get(['id', 'name', 'code']);
        $superAdminCount = $this->superAdminGrantCount();

        return view('admin.administrators.index', [
            'admins' => $admins,
            'roles' => $assignable,
            'directorates' => $directorates,
            'directorateNames' => $directorates->pluck('name', 'id'),
            'superAdminCount' => $superAdminCount,
            'temporaryPassword' => $request->session()->get('temporary_password'),
            'me' => $me,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_number' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'role' => ['required', Rule::in(AdminPermissions::ADMIN_ROLES)],
            'scope_id' => ['nullable', 'uuid', 'exists:directorates,id', 'required_if:role,directorate_admin'],
            'temporary_password' => ['nullable', 'boolean'],
        ], [
            'service_number.regex' => 'Service Numbers contain digits only.',
            'scope_id.required_if' => 'Choose the directorate this administrator will look after.',
        ]);
        $actor = $request->user();

        if ($data['role'] === 'super_admin' && ! $actor->isSuperAdmin()) {
            return back()->withInput()->with('error', 'Only a super administrator can appoint another super administrator.');
        }

        $user = User::where('service_number', $data['service_number'])->first();
        if (! $user) {
            return back()->withInput()->withErrors(['service_number' => 'No NISconnect account uses that Service Number. The officer must register in the app first.']);
        }
        if (! $user->isActive()) {
            return back()->withInput()->withErrors(['service_number' => 'That account is not active. Reactivate it before appointing them.']);
        }
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return back()->withInput()->with('error', 'Only a super administrator can change a super administrator\'s roles.');
        }

        $role = Role::where('name', $data['role'])->firstOrFail();
        $scopeId = $data['role'] === 'directorate_admin' ? $data['scope_id'] : null;
        $scopeType = $scopeId ? 'directorate' : null;

        $exists = UserRole::where('user_id', $user->id)->where('role_id', $role->id)
            ->where('scope_type', $scopeType)->where('scope_id', $scopeId)->exists();
        if ($exists) {
            return back()->withInput()->with('warning', "{$user->display_name} already holds that role.");
        }

        $grant = UserRole::create([
            'user_id' => $user->id, 'role_id' => $role->id,
            'scope_type' => $scopeType, 'scope_id' => $scopeId, 'granted_by' => $actor->id,
        ]);
        $this->audit->log('admin.role.granted', actorId: $actor->id, resourceType: 'user', resourceId: $user->id,
            metadata: ['role' => $role->name, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'user_role_id' => $grant->id]);
        $this->audit->security('admin_role_granted', userId: $user->id, severity: 'warning',
            metadata: ['role' => $role->name, 'by' => $actor->id]);

        $message = "{$user->display_name} is now a {$role->label}.";
        if (empty($user->password_hash)) {
            if ($request->boolean('temporary_password')) {
                $this->issueTemporaryPassword($request, $user, 'admin.password.temporary_issued');

                return redirect()->route('admin.administrators.index')->with('status', $message);
            }

            return redirect()->route('admin.administrators.index')->with('status', $message)
                ->with('warning', 'This officer has no portal password yet. Use "Reset password" to issue a temporary one.');
        }

        return redirect()->route('admin.administrators.index')->with('status', $message);
    }

    public function revoke(Request $request, UserRole $userRole): RedirectResponse
    {
        $userRole->load('role', 'user');
        $actor = $request->user();
        $target = $userRole->user;
        $roleName = $userRole->role?->name;

        if (! in_array($roleName, AdminPermissions::ADMIN_ROLES, true)) {
            return back()->with('error', 'Only administrator roles can be removed here.');
        }
        if ($target && $target->id === $actor->id) {
            return back()->with('error', 'You cannot remove your own administrator role. Ask another administrator.');
        }
        if (($roleName === 'super_admin' || $target?->isSuperAdmin()) && ! $actor->isSuperAdmin()) {
            return back()->with('error', 'Only a super administrator can change a super administrator\'s roles.');
        }
        if ($roleName === 'super_admin' && $this->superAdminGrantCount($userRole->id) < 1) {
            return back()->with('error', 'This is the last super administrator. Appoint another before removing this one.');
        }

        $meta = ['role' => $roleName, 'scope_type' => $userRole->scope_type, 'scope_id' => $userRole->scope_id];
        $userRole->delete();
        $this->audit->log('admin.role.revoked', actorId: $actor->id, resourceType: 'user', resourceId: $userRole->user_id, metadata: $meta);
        $this->audit->security('admin_role_revoked', userId: $userRole->user_id, severity: 'warning',
            metadata: $meta + ['by' => $actor->id]);

        return back()->with('status', ($target?->display_name ?? 'The officer').' no longer holds the '.$userRole->role->label.' role.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($error = $this->guard($request->user(), $user)) {
            return back()->with('error', $error);
        }

        $this->issueTemporaryPassword($request, $user, 'admin.password.reset');
        // End any portal sessions the administrator still has open.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return back()->with('status', "A temporary password was issued for {$user->display_name}.");
    }

    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        if ($error = $this->guard($request->user(), $user)) {
            return back()->with('error', $error);
        }

        $user->forceFill([
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null,
        ])->save();
        $this->audit->log('admin.2fa.reset', actorId: $request->user()->id, resourceType: 'user', resourceId: $user->id);
        $this->audit->security('admin_2fa_reset', userId: $user->id, severity: 'warning', metadata: ['by' => $request->user()->id]);

        return back()->with('status', "Two-factor authentication was reset for {$user->display_name}. They will set it up again at next sign-in.");
    }

    /** Sets a random temporary password, flashes it once, and records the event (never the password). */
    private function issueTemporaryPassword(Request $request, User $user, string $action): void
    {
        $password = $this->generatePassword(max(16, (int) $this->settings->get('admin_password_min_length')));
        $user->forceFill([
            'password_hash' => Hash::make($password),
            'password_changed_at' => now(),
            'must_change_password' => true,
        ])->save();

        $request->session()->flash('temporary_password', [
            'name' => $user->display_name, 'service_number' => $user->service_number, 'password' => $password,
        ]);
        $this->audit->log($action, actorId: $request->user()->id, resourceType: 'user', resourceId: $user->id);
        $this->audit->security('admin_password_reset', userId: $user->id, severity: 'warning', metadata: ['by' => $request->user()->id]);
    }

    /** Random password with upper, lower, digits and symbols (no look-alike characters). */
    private function generatePassword(int $length): string
    {
        $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '!@#$%*?-+='];
        $chars = array_map(fn ($s) => $s[random_int(0, strlen($s) - 1)], $sets);
        $all = implode('', $sets);
        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    private function guard(User $actor, User $target): ?string
    {
        if ($actor->id === $target->id) {
            return 'Use "My account" to change your own password or two-factor settings.';
        }
        if (! $target->roles()->whereHas('role', fn ($q) => $q->whereIn('name', AdminPermissions::ADMIN_ROLES))->exists()) {
            return 'That officer is not an administrator.';
        }
        if ($target->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return 'Only a super administrator can change a super administrator\'s account.';
        }

        return null;
    }

    /** Super administrator grants held by active accounts, optionally excluding one grant. */
    private function superAdminGrantCount(?string $exceptId = null): int
    {
        return UserRole::when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->whereHas('role', fn ($q) => $q->where('name', 'super_admin'))
            ->whereHas('user', fn ($q) => $q->where('account_state', User::STATE_ACTIVE))
            ->count();
    }
}
