<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\SystemController;
use App\Models\AuditLog;
use App\Services\Admin\SettingsService;

class SettingsSystemAdminTest extends AdminTestCase
{
    private function payload(array $overrides = []): array
    {
        $values = app(SettingsService::class)->all();
        $out = [];
        foreach ($values as $k => $v) {
            $out[$k] = is_bool($v) ? ($v ? '1' : '0') : $v;
        }

        return array_merge($out, $overrides);
    }

    public function test_settings_page_shows_form_and_environment(): void
    {
        $this->asAdmin()->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('System settings')
            ->assertSee('Allow new officer registration')
            ->assertSee('Maximum signed-in devices per officer')
            ->assertSee('name="registration_open" value="0"', false)
            ->assertSee('min="1"', false)
            ->assertSee('Environment')
            ->assertSee('Personnel provider')
            ->assertSee('Verification codes are returned in API responses')
            ->assertDontSee((string) config('app.key'))
            ->assertDontSee('devsecret0123456789abcdef');
    }

    public function test_update_saves_changes_and_audits_from_to(): void
    {
        $admin = $this->makeAdmin();

        $this->asAdmin($admin)->put(route('admin.settings.update'), $this->payload([
            'registration_open' => '0', 'max_devices_per_officer' => 3,
        ]))->assertRedirect()->assertSessionHas('status', '2 settings saved.');

        $settings = app(SettingsService::class);
        $this->assertFalse($settings->get('registration_open'));
        $this->assertSame(3, $settings->get('max_devices_per_officer'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated', 'actor_id' => $admin->id]);

        $log = AuditLog::where('action', 'settings.updated')->first();
        $this->assertEquals(['from' => true, 'to' => false], $log->metadata['changes']['registration_open']);
        $this->assertEquals(['from' => 5, 'to' => 3], $log->metadata['changes']['max_devices_per_officer']);

        // Saving again without changes writes nothing new.
        $this->asAdmin($admin)->put(route('admin.settings.update'), $this->payload())
            ->assertSessionHas('status', 'No changes to save.');
        $this->assertSame(1, AuditLog::where('action', 'settings.updated')->count());
    }

    public function test_update_validates_against_definitions(): void
    {
        $admin = $this->makeAdmin();
        $this->asAdmin($admin)->put(route('admin.settings.update'), $this->payload(['max_devices_per_officer' => 99]))
            ->assertSessionHasErrors('max_devices_per_officer');
        $this->asAdmin($admin)->put(route('admin.settings.update'), $this->payload(['admin_session_timeout' => 1]))
            ->assertSessionHasErrors('admin_session_timeout');
        $this->asAdmin($admin)->put(route('admin.settings.update'), $this->payload(['registration_open' => 'maybe']))
            ->assertSessionHasErrors('registration_open');
        $this->assertSame(5, app(SettingsService::class)->get('max_devices_per_officer'));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_settings_require_settings_manage(): void
    {
        $nisAdmin = $this->makeAdmin('nis_admin'); // no settings.manage by default
        $this->asAdmin($nisAdmin)->get(route('admin.settings.index'))->assertForbidden();
        $this->asAdmin($nisAdmin)->put(route('admin.settings.update'), $this->payload())->assertForbidden();
    }

    public function test_system_page_shows_health_checks_and_checklist(): void
    {
        $this->asAdmin()->get(route('admin.system.index'))
            ->assertOk()
            ->assertSee('System health')
            ->assertSee('Database')
            ->assertSee('PostgreSQL')
            ->assertSee('Cache')
            ->assertSee('Queue')
            ->assertSee('media')
            ->assertSee('Realtime broadcasting')
            ->assertSee('Scheduler')
            ->assertSee('No heartbeat seen')
            ->assertSee('Disk space')
            ->assertSee('Production checklist')
            ->assertSee('Real personnel source connected')
            ->assertSee('At least one Super Administrator uses two-factor authentication')
            ->assertSee(PHP_VERSION)
            ->assertDontSee('devsecret0123456789abcdef');
    }

    public function test_scheduler_heartbeat_is_reported(): void
    {
        SystemController::recordHeartbeat();
        $this->asAdmin()->get(route('admin.system.index'))->assertOk()->assertSee('Last heartbeat');
    }

    public function test_super_admin_2fa_checklist_item_passes_when_enabled(): void
    {
        $admin = $this->makeAdmin();
        $admin->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        $html = $this->asAdmin($admin)->get(route('admin.system.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Pass\s*<\/span>\s*<\/td>\s*<td>\s*<div class="cell-title">At least one Super Administrator/', $html);
    }

    public function test_system_requires_system_view(): void
    {
        $this->asAdmin($this->makeAdmin('group_admin'))->get(route('admin.system.index'))->assertForbidden();
        $this->asAdmin($this->makeAdmin('security_admin'))->get(route('admin.system.index'))->assertOk();
    }
}
