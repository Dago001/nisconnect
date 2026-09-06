<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'super_admin', 'label' => 'Super Administrator', 'scope_type' => null],
            ['name' => 'nis_admin', 'label' => 'NIS Administrator', 'scope_type' => null],
            ['name' => 'directorate_admin', 'label' => 'Directorate Administrator', 'scope_type' => 'directorate'],
            ['name' => 'group_admin', 'label' => 'Group Administrator', 'scope_type' => 'group'],
            ['name' => 'security_admin', 'label' => 'Security Administrator', 'scope_type' => null],
            ['name' => 'officer', 'label' => 'Standard Officer', 'scope_type' => null],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }

        $permissions = [
            ['name' => 'users.view', 'group' => 'users'],
            ['name' => 'users.suspend', 'group' => 'users'],
            ['name' => 'users.reactivate', 'group' => 'users'],
            ['name' => 'devices.revoke', 'group' => 'devices'],
            ['name' => 'groups.manage', 'group' => 'groups'],
            ['name' => 'channels.manage', 'group' => 'channels'],
            ['name' => 'reports.review', 'group' => 'safety'],
            ['name' => 'audit.view', 'group' => 'security'],
            ['name' => 'security.view', 'group' => 'security'],
            ['name' => 'messages.send', 'group' => 'messaging'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(['name' => $perm['name']], [
                'label' => ucwords(str_replace(['.', '_'], ' ', $perm['name'])),
                'group' => $perm['group'],
            ]);
        }

        // Super admin gets everything.
        $superAdmin = Role::where('name', 'super_admin')->first();
        $superAdmin->permissions()->sync(Permission::pluck('id'));

        // Officer gets baseline messaging.
        $officer = Role::where('name', 'officer')->first();
        $officer->permissions()->sync(Permission::whereIn('name', ['messages.send', 'users.view'])->pluck('id'));

        // Security admin gets audit/security.
        $security = Role::where('name', 'security_admin')->first();
        $security->permissions()->sync(
            Permission::whereIn('name', ['audit.view', 'security.view', 'devices.revoke', 'users.view'])->pluck('id')
        );
    }
}
