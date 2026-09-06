<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private array $device = ['name' => 'New Phone', 'platform' => 'android'];

    public function test_full_recovery_flow_resets_pin_and_revokes_old_devices(): void
    {
        $user = User::factory()->create(['service_number' => '505050']);
        $oldDevice = Device::create([
            'user_id' => $user->id, 'name' => 'Lost Phone', 'platform' => 'android',
            'status' => Device::STATUS_ACTIVE, 'last_active_at' => now(),
        ]);
        $oldToken = $user->createToken('old', ['officer'])->plainTextToken;

        // Step 1 — start (OTP exposed in test env).
        $start = $this->postJson('/api/v1/auth/recover/start', ['service_number' => '505050']);
        $start->assertOk()->assertJsonStructure(['verification_id', 'debug_code']);
        $id = $start->json('verification_id');
        $code = $start->json('debug_code');

        // Step 2 — verify OTP.
        $this->postJson('/api/v1/auth/recover/verify', ['verification_id' => $id, 'code' => $code])
            ->assertOk()->assertJsonPath('verified', true);

        // Step 3 — reset PIN + register new device.
        $this->postJson('/api/v1/auth/recover/reset', [
            'verification_id' => $id, 'pin' => '5555', 'device' => $this->device,
        ])->assertOk()->assertJsonStructure(['access_token']);

        // New PIN works.
        $this->assertTrue(Hash::check('5555', $user->fresh()->pin_hash));
        // Old device revoked, old token dead.
        $this->assertDatabaseHas('devices', ['id' => $oldDevice->id, 'status' => Device::STATUS_REVOKED]);
        $this->app['auth']->forgetGuards();
        $this->withToken($oldToken)->getJson('/api/v1/users/me')->assertUnauthorized();
        // Security event raised.
        $this->assertDatabaseHas('security_events', ['event' => 'account_recovered', 'user_id' => $user->id]);
    }

    public function test_recovery_start_is_generic_for_unknown_service_number(): void
    {
        // Unknown SN: still 200 with a verification_id, no code, no signal.
        $res = $this->postJson('/api/v1/auth/recover/start', ['service_number' => '060606']);
        $res->assertOk()->assertJsonStructure(['verification_id']);
        $this->assertNull($res->json('debug_code'));
    }

    public function test_cannot_reset_without_verifying_otp(): void
    {
        User::factory()->create(['service_number' => '707070']);
        $id = $this->postJson('/api/v1/auth/recover/start', ['service_number' => '707070'])->json('verification_id');

        // Skip verify — reset must be refused.
        $this->postJson('/api/v1/auth/recover/reset', [
            'verification_id' => $id, 'pin' => '1111', 'device' => $this->device,
        ])->assertStatus(422);
    }

    public function test_wrong_recovery_otp_is_rejected(): void
    {
        User::factory()->create(['service_number' => '808080']);
        $id = $this->postJson('/api/v1/auth/recover/start', ['service_number' => '808080'])->json('verification_id');

        $this->postJson('/api/v1/auth/recover/verify', ['verification_id' => $id, 'code' => '000000'])
            ->assertStatus(422)->assertJsonPath('verified', false);
    }
}
