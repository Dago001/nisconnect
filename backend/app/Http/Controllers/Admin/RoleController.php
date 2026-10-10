<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\UserRole;
use App\Services\Support\AuditLogger;
use App\Support\AdminPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The roles × permissions matrix. Super administrators always hold everything. */
class RoleController extends Controller
{
    private const ORDER = ['super_admin', 'nis_admin', 'security_admin', 'directorate_admin', 'group_admin', 'officer'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $roles = Role::with('permissions:id,name')->get()
            ->sortBy(fn ($r) => ($i = array_search($r->name, self::ORDER, true)) === false ? 99 : $i)->values();

        $holders = UserRole::selectRaw('role_id, COUNT(DISTINCT user_id) as c')->groupBy('role_id')->pluck('c', 'role_id');

        $groups = [];
        foreach (AdminPermissions::all() as $name => [$label, $group]) {
            $groups[$group][$name] = $label;
        }

        $granted = $roles->mapWithKeys(fn ($r) => [
            $r->id => $r->name === 'super_admin' ? array_keys(AdminPermissions::all()) : $r->permissions->pluck('name')->all(),
        ]);

        return view('admin.roles.index', compact('roles', 'holders', 'groups', 'granted'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if ($role->name === 'super_admin') {
            return back()->with('error', 'Super administrators always hold every permission. This role cannot be edited.');
        }

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(AdminPermissions::all()))],
        ]);
        $wanted = array_values(array_unique($data['permissions'] ?? []));

        $before = $role->permissions()->pluck('name')->all();
        $role->permissions()->sync(Permission::whereIn('name', $wanted)->pluck('id'));

        $added = array_values(array_diff($wanted, $before));
        $removed = array_values(array_diff($before, $wanted));
        if ($added || $removed) {
            $this->audit->log('role.permissions.updated', actorId: $request->user()->id, resourceType: 'role', resourceId: $role->id,
                metadata: ['role' => $role->name, 'added' => $added, 'removed' => $removed]);
            $this->audit->security('role_permissions_changed', userId: $request->user()->id, severity: 'warning',
                metadata: ['role' => $role->name, 'added' => $added, 'removed' => $removed]);
        }

        return back()->with('status', $added || $removed
            ? "Permissions for {$role->label} saved (".count($added).' added, '.count($removed).' removed).'
            : "No changes to {$role->label}.");
    }
}
