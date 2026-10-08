<?php

namespace App\Support;

/**
 * The single catalogue of administration permissions. Routes are gated by
 * these names (`perm:` middleware); roles are granted them in the portal's
 * Roles & permissions matrix. Super administrators implicitly hold all.
 */
final class AdminPermissions
{
    /**
     * name => [label, group]
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function all(): array
    {
        return [
            'dashboard.view' => ['Open the administration portal', 'Portal'],
            'officers.view' => ['View officer accounts', 'Officers'],
            'officers.manage' => ['Suspend, reactivate, unlock and sign out officers', 'Officers'],
            'officers.export' => ['Export officer lists', 'Officers'],
            'personnel.view' => ['View personnel records', 'Personnel'],
            'personnel.manage' => ['Create and edit personnel records', 'Personnel'],
            'personnel.import' => ['Import personnel records (CSV)', 'Personnel'],
            'admins.manage' => ['Appoint and remove administrators', 'Access control'],
            'roles.manage' => ['Edit role permissions', 'Access control'],
            'org.manage' => ['Manage organisation structure', 'Organisation'],
            'groups.manage' => ['Manage groups', 'Organisation'],
            'channels.manage' => ['Manage official channels', 'Organisation'],
            'announcements.send' => ['Send announcements', 'Communication'],
            'reports.review' => ['Review reports and moderate', 'Safety'],
            'devices.view' => ['View registered devices', 'Security'],
            'devices.revoke' => ['Revoke devices', 'Security'],
            'audit.view' => ['View audit log', 'Security'],
            'audit.export' => ['Export audit and security logs', 'Security'],
            'security.view' => ['View security events', 'Security'],
            'settings.manage' => ['Change system settings', 'System'],
            'system.view' => ['View system health', 'System'],
            'messages.send' => ['Send messages (officer app)', 'Messaging'],
        ];
    }

    /**
     * Default grants per role. super_admin is implicit (everything).
     *
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        $all = array_keys(self::all());

        return [
            'super_admin' => $all,
            'nis_admin' => array_values(array_diff($all, ['roles.manage', 'settings.manage', 'admins.manage'])),
            'security_admin' => [
                'dashboard.view', 'officers.view', 'officers.manage', 'officers.export',
                'devices.view', 'devices.revoke', 'audit.view', 'audit.export',
                'security.view', 'reports.review', 'system.view', 'messages.send',
            ],
            'directorate_admin' => [
                'dashboard.view', 'officers.view', 'personnel.view', 'groups.manage',
                'channels.manage', 'announcements.send', 'reports.review', 'messages.send',
            ],
            'group_admin' => ['dashboard.view', 'groups.manage', 'messages.send'],
            'officer' => ['messages.send'],
        ];
    }

    /** Roles that may sign in to the portal. */
    public const ADMIN_ROLES = ['super_admin', 'nis_admin', 'security_admin', 'directorate_admin', 'group_admin'];
}
