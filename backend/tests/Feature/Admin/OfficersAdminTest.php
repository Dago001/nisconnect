<?php

namespace Tests\Feature\Admin;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

class OfficersAdminTest extends AdminTestCase
{
    private function officerWithDevice(array $attributes = []): array
    {
        $user = User::factory()->create($attributes);
        $device = Device::create(['user_id' => $user->id, 'name' => 'Pixel 8', 'platform' => 'android',
            'model' => 'Pixel 8', 'status' => Device::STATUS_ACTIVE, 'last_active_at' => now()]);
        $token = $user->createToken('Pixel 8', ['officer']);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();

        return [$user, $device];
    }

    public function test_index_lists_searches_and_filters_officers(): void
    {
        $a = User::factory()->create(['display_name' => 'Amina Bello', 'service_number' => '001234']);
        $a->personnelRecord->update(['command' => 'Lagos Command', 'rank' => 'Inspector']);
        $b = User::factory()->suspended()->create(['display_name' => 'Chidi Okafor']);

        $this->asAdmin()->get(route('admin.officers.index'))
            ->assertOk()->assertSee('Officers')->assertSee('Amina Bello')->assertSee('Chidi Okafor')->assertSee('001234');

        $this->get(route('admin.officers.index', ['q' => '0012']))->assertOk()->assertSee('Amina Bello')->assertDontSee('Chidi Okafor');
        $this->get(route('admin.officers.index', ['q' => 'chidi']))->assertOk()->assertSee('Chidi Okafor')->assertDontSee('Amina Bello');
        $this->get(route('admin.officers.index', ['state' => 'suspended']))->assertOk()->assertSee('Chidi Okafor')->assertDontSee('Amina Bello');
        $this->get(route('admin.officers.index', ['command' => 'Lagos Command']))->assertOk()->assertSee('Amina Bello')->assertDontSee('Chidi Okafor');
        $this->get(route('admin.officers.index', ['role' => 'super_admin', 'sort' => 'name']))->assertOk()->assertDontSee('Amina Bello');
        $this->get(route('admin.officers.index', ['q' => '%']))->assertOk()->assertSee('No officers found');
    }

    public function test_export_streams_csv_without_phone_numbers(): void
    {
        $u = User::factory()->create(['display_name' => 'Amina Bello', 'phone' => '+2348011112222']);

        $res = $this->asAdmin()->get(route('admin.officers.export'));
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString('Amina Bello', $csv);
        $this->assertStringContainsString($u->service_number, $csv);
        $this->assertStringNotContainsString('8011112222', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'officers.exported']);
    }

    public function test_show_page_renders_profile_devices_and_history(): void
    {
        [$user] = $this->officerWithDevice(['display_name' => 'Amina Bello']);
        $this->asAdmin()->get(route('admin.officers.show', $user))
            ->assertOk()->assertSee('Amina Bello')->assertSee('Personnel record')->assertSee('Pixel 8')
            ->assertSee('Suspend')->assertSee('Revoke');
    }

    public function test_update_display_name(): void
    {
        $admin = $this->makeAdmin();
        $user = User::factory()->create();
        $this->asAdmin($admin)->patch(route('admin.officers.update', $user), ['display_name' => 'New Name'])
            ->assertRedirect()->assertSessionHas('status');
        $this->assertSame('New Name', $user->fresh()->display_name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'officer.updated', 'resource_id' => $user->id, 'actor_id' => $admin->id]);
    }

    public function test_suspend_requires_reason_revokes_tokens_and_reactivate_restores(): void
    {
        [$user] = $this->officerWithDevice();
        $this->asAdmin()->post(route('admin.officers.suspend', $user))->assertSessionHasErrors('reason');
        $this->assertSame('active', $user->fresh()->account_state);

        $this->post(route('admin.officers.suspend', $user), ['reason' => 'Under investigation'])->assertSessionHas('status');
        $this->assertSame('suspended', $user->fresh()->account_state);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'officer.suspended', 'resource_id' => $user->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'account_suspended', 'user_id' => $user->id]);

        $this->post(route('admin.officers.reactivate', $user))->assertSessionHas('status');
        $this->assertSame('active', $user->fresh()->account_state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'officer.reactivated', 'resource_id' => $user->id]);
    }

    public function test_disabled_account_cannot_sign_in(): void
    {
        $user = User::factory()->create();
        $this->asAdmin()->post(route('admin.officers.disable', $user), ['reason' => 'Left the Service'])->assertSessionHas('status');
        $this->assertSame('disabled', $user->fresh()->account_state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'officer.disabled', 'resource_id' => $user->id]);

        $this->postJson('/api/v1/auth/login', [
            'service_number' => $user->service_number, 'pin' => '1234',
            'device' => ['name' => 'Phone', 'platform' => 'android'],
        ])->assertStatus(403);
    }

    public function test_unlock_clears_login_lockout(): void
    {
        $user = User::factory()->create(['account_state' => User::STATE_LOCKED]);
        $key = 'login-account:'.$user->service_number;
        for ($i = 0; $i < 6; $i++) {
            RateLimiter::hit($key, 900);
        }
        $this->asAdmin()->get(route('admin.officers.show', $user))->assertSee('Unlock sign-in');

        $this->post(route('admin.officers.unlock', $user))->assertSessionHas('status');
        $this->assertFalse(RateLimiter::tooManyAttempts($key, 5));
        $this->assertSame('active', $user->fresh()->account_state);
        $this->assertDatabaseHas('audit_logs', ['action' => 'officer.unlocked', 'resource_id' => $user->id]);
    }

    public function test_sign_out_everywhere_revokes_tokens_and_devices(): void
    {
        [$user, $device] = $this->officerWithDevice();
        $this->asAdmin()->post(route('admin.officers.sign-out', $user))->assertSessionHas('status');
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame('revoked', $device->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'officer.signed_out_everywhere', 'resource_id' => $user->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'signed_out_everywhere', 'user_id' => $user->id]);
    }

    public function test_guard_rules_protect_self_and_super_admins(): void
    {
        $nis = $this->makeAdmin('nis_admin');
        $super = $this->makeAdmin('super_admin');

        $this->asAdmin($nis)->post(route('admin.officers.suspend', $nis), ['reason' => 'test'])->assertSessionHas('error');
        $this->assertSame('active', $nis->fresh()->account_state);

        $this->post(route('admin.officers.suspend', $super), ['reason' => 'test'])->assertSessionHas('error');
        $this->post(route('admin.officers.disable', $super), ['reason' => 'test'])->assertSessionHas('error');
        $this->assertSame('active', $super->fresh()->account_state);
        $this->get(route('admin.officers.show', $super))->assertOk()->assertSee('Only a super administrator');
    }

    public function test_view_only_admin_cannot_manage(): void
    {
        $directorate = $this->makeAdmin('directorate_admin');
        $user = User::factory()->create();
        $this->asAdmin($directorate)->get(route('admin.officers.show', $user))->assertOk()->assertDontSee('Sign out everywhere');
        $this->post(route('admin.officers.suspend', $user), ['reason' => 'x'])->assertForbidden();
        $this->get(route('admin.officers.export'))->assertForbidden();
    }
}
