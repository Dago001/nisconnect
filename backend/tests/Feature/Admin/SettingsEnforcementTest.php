<?php

namespace Tests\Feature\Admin;

use App\Models\Device;
use App\Models\User;
use App\Services\Admin\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The officer API honours the settings administrators change in the portal. */
class SettingsEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const CLOSED = 'Registration is currently closed. Contact your administrator.';

    private function setting(string $key, mixed $value): void
    {
        app(SettingsService::class)->update([$key => $value], null);
    }

    /** Runs onboarding steps 1–3 and returns the verification id. */
    private function verifiedSession(string $serviceNumber = '123456'): string
    {
        $id = $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => $serviceNumber])
            ->assertOk()->json('verification_id');
        $code = $this->postJson('/api/v1/auth/confirm-identity', ['verification_id' => $id, 'phone' => '+2348030000000'])
            ->json('debug_code');
        $this->postJson('/api/v1/auth/verify-otp', ['verification_id' => $id, 'code' => $code])->assertOk();

        return $id;
    }

    public function test_closed_registration_refuses_new_officers(): void
    {
        $this->setting('registration_open', false);

        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '123456'])
            ->assertForbidden()->assertJsonPath('message', self::CLOSED)
            ->assertJsonMissingPath('verification_id');
        // Same response for numbers that are not in the personnel source (no enumeration signal).
        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '888777'])
            ->assertForbidden()->assertJsonPath('message', self::CLOSED);

        $this->assertDatabaseHas('audit_logs', ['action' => 'service_number.verify_refused', 'result' => 'denied']);
        $this->assertDatabaseMissing('users', ['service_number' => '123456']);
    }

    public function test_open_registration_still_works(): void
    {
        $this->setting('registration_open', true);
        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '123456'])
            ->assertOk()->assertJsonPath('verified', true);
    }

    public function test_registration_closed_mid_flow_blocks_account_creation(): void
    {
        $id = $this->verifiedSession();
        $this->setting('registration_open', false);

        $this->postJson('/api/v1/auth/set-credentials', [
            'verification_id' => $id, 'pin' => '1234', 'device' => ['name' => 'Pixel', 'platform' => 'android'],
        ])->assertForbidden()->assertJsonPath('message', self::CLOSED);
        $this->assertDatabaseMissing('users', ['service_number' => '123456']);
    }

    public function test_existing_officers_can_still_sign_in_and_recover_when_closed(): void
    {
        $user = User::factory()->create(['service_number' => '654321']);
        $this->setting('registration_open', false);

        // Existing account holders are not refused at the verify step.
        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '654321'])
            ->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'service_number' => '654321', 'pin' => '1234', 'device' => ['name' => 'iPhone', 'platform' => 'ios'],
        ])->assertOk()->assertJsonStructure(['access_token']);

        $start = $this->postJson('/api/v1/auth/recover/start', ['service_number' => '654321'])->assertOk();
        $id = $start->json('verification_id');
        $this->postJson('/api/v1/auth/recover/verify', ['verification_id' => $id, 'code' => $start->json('debug_code')])
            ->assertOk()->assertJsonPath('verified', true);
        $this->postJson('/api/v1/auth/recover/reset', [
            'verification_id' => $id, 'pin' => '5555', 'device' => ['name' => 'New Phone', 'platform' => 'android'],
        ])->assertOk()->assertJsonStructure(['access_token']);
        $this->assertTrue($user->fresh()->isActive());
    }

    public function test_login_beyond_device_limit_revokes_least_recently_active_device(): void
    {
        $this->setting('max_devices_per_officer', 2);
        $user = User::factory()->create(['service_number' => '222333']);

        $oldest = Device::create(['user_id' => $user->id, 'name' => 'Old Tablet', 'platform' => 'android',
            'status' => Device::STATUS_ACTIVE, 'last_active_at' => now()->subDays(30)]);
        $recent = Device::create(['user_id' => $user->id, 'name' => 'Work Phone', 'platform' => 'android',
            'status' => Device::STATUS_ACTIVE, 'last_active_at' => now()->subHour()]);
        $oldToken = $user->createToken('Old Tablet', ['officer']);
        $oldToken->accessToken->forceFill(['device_id' => $oldest->id])->save();
        $keptToken = $user->createToken('Work Phone', ['officer']);
        $keptToken->accessToken->forceFill(['device_id' => $recent->id])->save();

        $this->postJson('/api/v1/auth/login', [
            'service_number' => '222333', 'pin' => '1234', 'device' => ['name' => 'iPhone 15', 'platform' => 'ios'],
        ])->assertOk();

        $this->assertSame(Device::STATUS_REVOKED, $oldest->fresh()->status);
        $this->assertSame(Device::STATUS_ACTIVE, $recent->fresh()->status);
        $this->assertSame(2, $user->devices()->where('status', Device::STATUS_ACTIVE)->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $oldToken->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $keptToken->accessToken->id]);
        $this->assertDatabaseHas('security_events', ['event' => 'device_limit_revoked', 'user_id' => $user->id]);
    }

    public function test_login_within_limit_and_same_device_relogin_revoke_nothing(): void
    {
        $this->setting('max_devices_per_officer', 2);
        $user = User::factory()->create(['service_number' => '222444']);
        Device::create(['user_id' => $user->id, 'name' => 'Work Phone', 'platform' => 'android',
            'status' => Device::STATUS_ACTIVE, 'last_active_at' => now()->subDay()]);
        $login = fn () => $this->postJson('/api/v1/auth/login', [
            'service_number' => '222444', 'pin' => '1234', 'device' => ['name' => 'iPhone 15', 'platform' => 'ios'],
        ])->assertOk();

        $login();
        $login(); // signing in again on the same device does not count twice

        $this->assertSame(2, $user->devices()->where('status', Device::STATUS_ACTIVE)->count());
        $this->assertDatabaseMissing('security_events', ['event' => 'device_limit_revoked']);
    }

    public function test_limit_of_one_keeps_only_the_newest_device(): void
    {
        $this->setting('max_devices_per_officer', 1);
        $user = User::factory()->create(['service_number' => '222555']);
        foreach (['A', 'B'] as $i => $name) {
            Device::create(['user_id' => $user->id, 'name' => $name, 'platform' => 'android',
                'status' => Device::STATUS_ACTIVE, 'last_active_at' => now()->subDays($i + 1)]);
        }

        $this->postJson('/api/v1/auth/login', [
            'service_number' => '222555', 'pin' => '1234', 'device' => ['name' => 'C', 'platform' => 'web'],
        ])->assertOk();

        $this->assertSame(['C'], $user->devices()->where('status', Device::STATUS_ACTIVE)->pluck('name')->all());
    }
}
