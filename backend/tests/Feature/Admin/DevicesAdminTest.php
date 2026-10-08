<?php

namespace Tests\Feature\Admin;

use App\Models\Device;
use App\Models\User;

class DevicesAdminTest extends AdminTestCase
{
    private function device(User $user, array $attributes = []): Device
    {
        return Device::create($attributes + ['user_id' => $user->id, 'name' => 'Galaxy S24', 'platform' => 'android',
            'model' => 'SM-S921', 'app_version' => '1.4.0', 'status' => Device::STATUS_ACTIVE, 'last_active_at' => now()]);
    }

    public function test_index_lists_and_filters_devices(): void
    {
        $amina = User::factory()->create(['display_name' => 'Amina Bello']);
        $chidi = User::factory()->create(['display_name' => 'Chidi Okafor']);
        $this->device($amina);
        $this->device($chidi, ['name' => 'iPhone 15', 'platform' => 'ios', 'status' => Device::STATUS_REVOKED]);

        $this->asAdmin()->get(route('admin.devices.index'))
            ->assertOk()->assertSee('Devices')->assertSee('Galaxy S24')->assertSee('iPhone 15')->assertSee('Amina Bello');
        $this->get(route('admin.devices.index', ['platform' => 'ios']))->assertSee('iPhone 15')->assertDontSee('Galaxy S24');
        $this->get(route('admin.devices.index', ['status' => 'active']))->assertSee('Galaxy S24')->assertDontSee('iPhone 15');
        $this->get(route('admin.devices.index', ['q' => $chidi->service_number]))->assertSee('iPhone 15')->assertDontSee('Galaxy S24');
        $this->get(route('admin.devices.index', ['q' => 'nobody-matches']))->assertSee('No devices found');
    }

    public function test_revoke_device_deletes_its_tokens_only(): void
    {
        $user = User::factory()->create();
        $device = $this->device($user);
        $other = $this->device($user, ['name' => 'Tablet']);
        $user->createToken('a')->accessToken->forceFill(['device_id' => $device->id])->save();
        $user->createToken('b')->accessToken->forceFill(['device_id' => $other->id])->save();

        $this->asAdmin()->post(route('admin.devices.revoke', $device))->assertSessionHas('status');
        $this->assertSame('revoked', $device->fresh()->status);
        $this->assertSame(1, $user->tokens()->count());
        $this->assertSame($other->id, $user->tokens()->first()->device_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.revoked', 'resource_id' => $device->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'device_revoked', 'user_id' => $user->id]);
    }

    public function test_permissions_and_super_admin_protection(): void
    {
        $security = $this->makeAdmin('security_admin');
        $super = $this->makeAdmin();
        $device = $this->device($super);
        $this->asAdmin($security)->post(route('admin.devices.revoke', $device))->assertSessionHas('error');
        $this->assertSame('active', $device->fresh()->status);

        $this->asAdmin($this->makeAdmin('directorate_admin'))->get(route('admin.devices.index'))->assertForbidden();
    }
}
