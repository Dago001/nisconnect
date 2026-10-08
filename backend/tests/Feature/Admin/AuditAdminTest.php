<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\SecurityEvent;
use App\Models\User;

class AuditAdminTest extends AdminTestCase
{
    private function seedLogs(User $actor): void
    {
        AuditLog::create(['actor_id' => $actor->id, 'action' => 'officer.suspended', 'resource_type' => 'user',
            'result' => 'success', 'ip' => '10.0.0.9', 'metadata' => ['reason' => 'policy-breach'], 'created_at' => now()->subDays(1)]);
        AuditLog::create(['action' => 'login.failed', 'resource_type' => 'user', 'result' => 'failure',
            'metadata' => ['service_number' => '777001'], 'created_at' => now()->subDays(10)]);
        AuditLog::create(['action' => 'group.created', 'resource_type' => 'group', 'result' => 'success', 'created_at' => now()]);
    }

    public function test_audit_log_lists_and_filters(): void
    {
        $admin = $this->makeAdmin();
        $actor = User::factory()->create(['display_name' => 'Chidi Actor', 'service_number' => '005555']);
        $this->seedLogs($actor);

        $this->asAdmin($admin)->get(route('admin.audit.index'))
            ->assertOk()->assertSee('Audit log')->assertSee('officer.suspended')->assertSee('Chidi Actor')
            ->assertSee('005555')->assertSee('10.0.0.9')->assertSee('policy-breach')->assertSee('login.failed');

        $this->asAdmin($admin)->get(route('admin.audit.index', ['action' => 'officer']))
            ->assertOk()->assertSee('officer.suspended')->assertDontSee('login.failed')->assertDontSee('group.created');
        $this->asAdmin($admin)->get(route('admin.audit.index', ['actor' => '005555']))
            ->assertOk()->assertSee('officer.suspended')->assertDontSee('group.created');
        $this->asAdmin($admin)->get(route('admin.audit.index', ['result' => 'failure']))
            ->assertOk()->assertSee('login.failed')->assertDontSee('officer.suspended');
        $this->asAdmin($admin)->get(route('admin.audit.index', ['resource_type' => 'group']))
            ->assertOk()->assertSee('group.created')->assertDontSee('login.failed');
        $this->asAdmin($admin)->get(route('admin.audit.index', ['from' => now()->subDays(3)->toDateString(), 'to' => now()->toDateString()]))
            ->assertOk()->assertSee('officer.suspended')->assertDontSee('login.failed');
    }

    public function test_audit_filters_are_validated(): void
    {
        $admin = $this->makeAdmin();
        $this->asAdmin($admin)->get(route('admin.audit.index', ['from' => '2026-10-08', 'to' => '2026-10-01']))
            ->assertSessionHasErrors('to');
        $this->asAdmin($admin)->get(route('admin.audit.index', ['actor' => 'abc']))
            ->assertSessionHasErrors('actor');
        $this->asAdmin($admin)->get(route('admin.audit.index', ['from' => 'yesterday']))
            ->assertSessionHasErrors('from');
    }

    public function test_audit_export_streams_filtered_csv_and_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $actor = User::factory()->create(['display_name' => 'Chidi Actor', 'service_number' => '005555']);
        $this->seedLogs($actor);

        $response = $this->asAdmin($admin)->get(route('admin.audit.export', ['action' => 'officer']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $csv = $response->streamedContent();
        $this->assertStringContainsString('officer.suspended', $csv);
        $this->assertStringContainsString('005555', $csv);
        $this->assertStringNotContainsString('login.failed', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'audit.exported', 'actor_id' => $admin->id]);
    }

    public function test_security_events_list_filter_and_export(): void
    {
        $admin = $this->makeAdmin();
        $officer = User::factory()->create(['display_name' => 'Dayo Officer', 'service_number' => '008888']);
        SecurityEvent::create(['user_id' => $officer->id, 'event' => 'failed_login', 'severity' => 'warning', 'ip' => '10.1.1.1', 'created_at' => now()]);
        SecurityEvent::create(['event' => 'new_device_login', 'severity' => 'info', 'ip' => '10.2.2.2', 'metadata' => ['platform' => 'ios'], 'created_at' => now()->subDays(20)]);

        $this->asAdmin($admin)->get(route('admin.security.index'))
            ->assertOk()->assertSee('Security events')->assertSee('failed_login')->assertSee('Dayo Officer')->assertSee('new_device_login');
        $this->asAdmin($admin)->get(route('admin.security.index', ['severity' => 'warning']))
            ->assertOk()->assertSee('10.1.1.1')->assertDontSee('10.2.2.2');
        $this->asAdmin($admin)->get(route('admin.security.index', ['officer' => '008888']))
            ->assertOk()->assertSee('10.1.1.1')->assertDontSee('10.2.2.2');
        $this->asAdmin($admin)->get(route('admin.security.index', ['event' => 'device']))
            ->assertOk()->assertSee('10.2.2.2')->assertDontSee('10.1.1.1');
        $this->asAdmin($admin)->get(route('admin.security.index', ['from' => now()->subDays(2)->toDateString()]))
            ->assertOk()->assertSee('10.1.1.1')->assertDontSee('10.2.2.2');
        $this->asAdmin($admin)->get(route('admin.security.index', ['severity' => 'nope']))
            ->assertSessionHasErrors('severity');

        $csv = $this->asAdmin($admin)->get(route('admin.security.export', ['severity' => 'warning']))
            ->assertOk()->streamedContent();
        $this->assertStringContainsString('failed_login', $csv);
        $this->assertStringContainsString('008888', $csv);
        $this->assertStringNotContainsString('new_device_login', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'security_events.exported', 'actor_id' => $admin->id]);
    }

    public function test_permissions_are_enforced(): void
    {
        $groupAdmin = $this->makeAdmin('group_admin');
        $this->asAdmin($groupAdmin)->get(route('admin.audit.index'))->assertForbidden();
        $this->asAdmin($groupAdmin)->get(route('admin.security.index'))->assertForbidden();
        $this->asAdmin($groupAdmin)->get(route('admin.audit.export'))->assertForbidden();

        // security_admin can view and export
        $sec = $this->makeAdmin('security_admin');
        $this->asAdmin($sec)->get(route('admin.audit.index'))->assertOk()->assertSee('Export CSV');
    }
}
