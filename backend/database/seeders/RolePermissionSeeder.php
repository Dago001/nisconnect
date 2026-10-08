<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;

/**
 * Idempotent: safe to re-run on every deploy. Creates roles and the permission
 * catalogue. Default grants are applied only to roles that have none yet, so
 * changes made in the portal's permission matrix are preserved.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'label' => 'Super Administrator', 'scope_type' => null,
                'description' => 'Full control of NISconnect, including access control and system settings.'],
            ['name' => 'nis_admin', 'label' => 'NIS Administrator', 'scope_type' => null,
                'description' => 'Day-to-day administration of officers, personnel, groups and channels.'],
            ['name' => 'security_admin', 'label' => 'Security Administrator', 'scope_type' => null,
                'description' => 'Security monitoring, audit review, device control and moderation.'],
            ['name' => 'directorate_admin', 'label' => 'Directorate Administrator', 'scope_type' => 'directorate',
                'description' => 'Communication and groups for a directorate.'],
            ['name' => 'group_admin', 'label' => 'Group Administrator', 'scope_type' => 'group',
                'description' => 'Manages groups.'],
            ['name' => 'officer', 'label' => 'Standard Officer', 'scope_type' => null,
                'description' => 'Every registered officer.'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }

        foreach (AdminPermissions::all() as $name => [$label, $group]) {
            Permission::updateOrCreate(['name' => $name], ['label' => $label, 'group' => $group]);
        }
        // Drop permissions no longer in the catalogue.
        Permission::whereNotIn('name', array_keys(AdminPermissions::all()))->delete();

        foreach (AdminPermissions::defaults() as $roleName => $perms) {
            $role = Role::where('name', $roleName)->first();
            if ($roleName === 'super_admin' || ! $role->permissions()->exists()) {
                $role->permissions()->sync(Permission::whereIn('name', $perms)->pluck('id'));
            }
        }
    }
}
