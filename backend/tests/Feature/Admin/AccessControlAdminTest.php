<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Directorate;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\TwoFactorService;
use App\Support\AdminPermissions;
use Illuminate\Support\Facades\Hash;

class AccessControlAdminTest extends AdminTestCase
{
    public function test_administrators_page_lists_admins(): void
    {
        $nis = $this->makeAdmin('nis_admin', ['display_name' => 'Grace Admin']);
        User::factory()->create(['display_name' => 'Plain Officer']);

        $this->asAdmin()->get(route('admin.administrators.index'))
            ->assertOk()->assertSee('Administrators')->assertSee('Grace Admin')->assertSee('NIS Administrator')
            ->assertDontSee('Plain Officer')->assertSee('Appoint an administrator');
    }

    public function test_appoint_with_temporary_password_shown_once(): void
    {
        $admin = $this->makeAdmin();
        $officer = User::factory()->create(['service_number' => '001234']);

        $this->asAdmin($admin)->post(route('admin.administrators.store'), [
            'service_number' => '001234', 'role' => 'security_admin', 'temporary_password' => '1',
        ])->assertRedirect(route('admin.administrators.index'))->assertSessionHas('temporary_password');

        $officer->refresh();
        $this->assertTrue($officer->hasRole('security_admin'));
        $this->assertTrue($officer->must_change_password);
        $temp = session('temporary_password')['password'];
        $this->assertTrue(Hash::check($temp, $officer->password_hash));
        $this->assertGreaterThanOrEqual(16, strlen($temp));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.role.granted', 'resource_id' => $officer->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'admin_role_granted', 'user_id' => $officer->id]);

        $this->get(route('admin.administrators.index'))->assertSee($temp);
        $this->get(route('admin.administrators.index'))->assertDontSee($temp);

        // The password itself is never written to the audit trail.
        $this->assertDatabaseMissing('audit_logs', ['metadata' => json_encode(['password' => $temp])]);
    }

    public function test_appoint_validates_service_number_and_scope(): void
    {
        $this->asAdmin()->post(route('admin.administrators.store'), ['service_number' => '777777', 'role' => 'nis_admin'])
            ->assertSessionHasErrors('service_number');

        $officer = User::factory()->create();
        $this->post(route('admin.administrators.store'), ['service_number' => $officer->service_number, 'role' => 'directorate_admin'])
            ->assertSessionHasErrors('scope_id');

        $org = Organisation::create(['name' => 'NIS', 'code' => 'NIS']);
        $dir = Directorate::create(['organisation_id' => $org->id, 'name' => 'Border Management', 'code' => 'BM']);
        $this->post(route('admin.administrators.store'), [
            'service_number' => $officer->service_number, 'role' => 'directorate_admin', 'scope_id' => $dir->id,
        ])->assertSessionHas('status');
        $this->assertTrue($officer->fresh()->hasRole('directorate_admin', 'directorate', $dir->id));
    }

    public function test_only_super_admins_grant_super_admin(): void
    {
        $nis = $this->makeAdmin('nis_admin');
        $nis->roles()->first()->role->permissions()->attach(Permission::where('name', 'admins.manage')->value('id'));
        $officer = User::factory()->create();

        $this->asAdmin($nis->fresh())->post(route('admin.administrators.store'), ['service_number' => $officer->service_number, 'role' => 'super_admin'])
            ->assertSessionHas('error');
        $this->assertFalse($officer->fresh()->isSuperAdmin());
    }

    public function test_revoke_role_and_last_super_admin_protection(): void
    {
        $super = $this->makeAdmin();
        $nis = $this->makeAdmin('nis_admin');
        $nisGrant = $nis->roles()->first();

        $this->asAdmin($super)->delete(route('admin.administrators.revoke', $nisGrant))->assertSessionHas('status');
        $this->assertDatabaseMissing('user_roles', ['id' => $nisGrant->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.role.revoked', 'resource_id' => $nis->id]);

        // Cannot remove own role, nor the last super admin.
        $own = $super->roles()->first();
        $this->delete(route('admin.administrators.revoke', $own))->assertSessionHas('error');
        $this->assertDatabaseHas('user_roles', ['id' => $own->id]);

        $other = $this->makeAdmin();
        $this->delete(route('admin.administrators.revoke', $other->roles()->first()))->assertSessionHas('status');
        $this->assertFalse($other->fresh()->isSuperAdmin());
    }

    public function test_reset_password_and_two_factor(): void
    {
        $super = $this->makeAdmin();
        $nis = $this->makeAdmin('nis_admin');
        $svc = app(TwoFactorService::class);
        $nis->forceFill(['two_factor_secret' => $svc->generateSecret(), 'two_factor_confirmed_at' => now()])->save();

        $this->asAdmin($super)->post(route('admin.administrators.reset-password', $nis))->assertSessionHas('temporary_password');
        $nis->refresh();
        $this->assertTrue($nis->must_change_password);
        $this->assertFalse(Hash::check(self::PASSWORD, $nis->password_hash));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.password.reset', 'resource_id' => $nis->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'admin_password_reset', 'user_id' => $nis->id]);

        $this->post(route('admin.administrators.reset-two-factor', $nis))->assertSessionHas('status');
        $this->assertFalse($nis->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.2fa.reset', 'resource_id' => $nis->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'admin_2fa_reset', 'user_id' => $nis->id]);

        // Not on yourself.
        $this->post(route('admin.administrators.reset-password', $super))->assertSessionHas('error');
    }

    public function test_roles_matrix_renders_and_updates(): void
    {
        $role = Role::where('name', 'group_admin')->first();
        $this->asAdmin()->get(route('admin.roles.index'))
            ->assertOk()->assertSee('Roles &amp; permissions', false)->assertSee('Group Administrator')->assertSee('Locked')
            ->assertSee('View officer accounts');

        $this->put(route('admin.roles.update', $role), ['permissions' => ['dashboard.view', 'officers.view']])
            ->assertSessionHas('status');
        $this->assertEqualsCanonicalizing(['dashboard.view', 'officers.view'], $role->permissions()->pluck('name')->all());
        $log = AuditLog::where('action', 'role.permissions.updated')->first();
        $this->assertSame(['officers.view'], $log->metadata['added']);
        $this->assertEqualsCanonicalizing(['groups.manage', 'messages.send'], $log->metadata['removed']);

        $this->put(route('admin.roles.update', $role), ['permissions' => ['not.a.permission']])->assertSessionHasErrors();

        $super = Role::where('name', 'super_admin')->first();
        $this->put(route('admin.roles.update', $super), ['permissions' => []])->assertSessionHas('error');
        $this->assertSame(count(AdminPermissions::all()), $super->permissions()->count());
    }

    public function test_non_super_admin_cannot_reach_access_control(): void
    {
        $this->asAdmin($this->makeAdmin('nis_admin'))->get(route('admin.administrators.index'))->assertForbidden();
        $this->get(route('admin.roles.index'))->assertForbidden();
    }
}
