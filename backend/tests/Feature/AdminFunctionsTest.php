<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\Report;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminFunctionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['password_hash' => Hash::make('pw')])->save();
        UserRole::create(['user_id' => $u->id, 'role_id' => Role::where('name', 'super_admin')->first()->id]);

        return $u;
    }

    public function test_admin_can_review_a_report(): void
    {
        $admin = $this->admin();
        $reporter = User::factory()->create();
        $report = Report::create([
            'reporter_id' => $reporter->id, 'target_type' => 'user',
            'target_id' => $reporter->id, 'reason' => 'Spam', 'status' => 'open',
        ]);

        $this->actingAs($admin)->post(route('admin.reports.action', $report), ['status' => 'actioned'])
            ->assertRedirect();
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'actioned', 'reviewed_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.reviewed']);
    }

    public function test_admin_can_view_audit_and_security_pages(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.audit'))->assertOk()->assertSee('Audit log');
        $this->actingAs($admin)->get(route('admin.security'))->assertOk()->assertSee('Security events');
    }

    public function test_admin_can_create_channel_and_add_publisher(): void
    {
        $admin = $this->admin();
        $officer = User::factory()->create(['service_number' => '345678']);

        $this->actingAs($admin)->post(route('admin.org.channels.store'), [
            'name' => 'Service Headquarters Announcements',
        ])->assertRedirect();
        $channel = Channel::where('name', 'Service Headquarters Announcements')->firstOrFail();
        $this->assertDatabaseHas('channel_members', ['channel_id' => $channel->id, 'user_id' => $admin->id, 'role' => 'publisher']);

        $this->actingAs($admin)->post(route('admin.org.channels.publisher', $channel), [
            'service_number' => '345678',
        ])->assertRedirect();
        $this->assertDatabaseHas('channel_members', ['channel_id' => $channel->id, 'user_id' => $officer->id, 'role' => 'publisher']);
    }

    public function test_admin_can_create_organisational_group(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.org.groups.store'), [
            'name' => 'ICT/Cyber Security Directorate',
        ])->assertRedirect();
        $this->assertDatabaseHas('groups', ['name' => 'ICT/Cyber Security Directorate', 'type' => 'organisational']);
    }

    public function test_non_admin_cannot_reach_org_admin(): void
    {
        $officer = User::factory()->create();
        $officer->forceFill(['password_hash' => Hash::make('pw')])->save();
        $this->actingAs($officer)->get(route('admin.org'))->assertForbidden();
    }
}
