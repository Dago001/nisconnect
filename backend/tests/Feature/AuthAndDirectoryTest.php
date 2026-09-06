<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthAndDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_correct_pin_returns_token(): void
    {
        $user = User::factory()->create(['service_number' => '222333']);

        $res = $this->postJson('/api/v1/auth/login', [
            'service_number' => '222333',
            'pin' => '1234',
            'device' => ['name' => 'iPhone 15', 'platform' => 'ios'],
        ]);

        $res->assertOk()->assertJsonStructure(['access_token', 'user' => ['id']]);
        $this->assertDatabaseHas('devices', ['user_id' => $user->id, 'platform' => 'ios']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login.success']);
    }

    public function test_login_with_wrong_pin_is_rejected_generically(): void
    {
        User::factory()->create(['service_number' => '222444']);

        $this->postJson('/api/v1/auth/login', [
            'service_number' => '222444',
            'pin' => '9999',
            'device' => ['name' => 'iPhone 15', 'platform' => 'ios'],
        ])->assertStatus(401)->assertJsonPath('message', 'Invalid Service Number or credentials.');

        $this->assertDatabaseHas('security_events', ['event' => 'failed_login']);
    }

    public function test_suspended_account_cannot_login(): void
    {
        User::factory()->suspended()->create(['service_number' => '222555']);

        $this->postJson('/api/v1/auth/login', [
            'service_number' => '222555',
            'pin' => '1234',
            'device' => ['name' => 'X', 'platform' => 'android'],
        ])->assertStatus(403);
    }

    public function test_protected_route_requires_auth(): void
    {
        $this->getJson('/api/v1/users/me')->assertUnauthorized();
    }

    public function test_me_returns_current_officer(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.service_number', $user->service_number);
    }

    public function test_directory_search_requires_a_term(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/directory/search')->assertStatus(422);
    }

    public function test_directory_search_finds_officers_by_name(): void
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);

        $target = User::factory()->create(['display_name' => 'Chidi Okafor']);
        $target->personnelRecord->update(['surname' => 'Okafor', 'first_name' => 'Chidi']);

        $this->getJson('/api/v1/directory/search?q=Okafor')
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'Chidi Okafor')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_directory_search_excludes_self(): void
    {
        $me = User::factory()->create();
        $me->personnelRecord->update(['surname' => 'Selfsurname']);
        Sanctum::actingAs($me);

        $this->getJson('/api/v1/directory/search?q=Selfsurname')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_user_can_list_and_revoke_devices(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $device = Device::create([
            'user_id' => $user->id, 'name' => 'Old Tablet', 'platform' => 'android',
            'status' => Device::STATUS_ACTIVE, 'last_active_at' => now(),
        ]);

        $this->getJson('/api/v1/devices')->assertOk()
            ->assertJsonFragment(['name' => 'Old Tablet']);

        $this->deleteJson("/api/v1/devices/{$device->id}")->assertOk();
        $this->assertDatabaseHas('devices', ['id' => $device->id, 'status' => Device::STATUS_REVOKED]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device.removed']);
    }

    public function test_user_cannot_revoke_another_users_device(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $otherDevice = Device::create([
            'user_id' => $other->id, 'name' => 'Their Phone', 'platform' => 'ios',
            'status' => Device::STATUS_ACTIVE, 'last_active_at' => now(),
        ]);

        Sanctum::actingAs($me);
        $this->deleteJson("/api/v1/devices/{$otherDevice->id}")->assertForbidden();
    }
}
