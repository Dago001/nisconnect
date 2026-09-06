<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private array $device = [
        'name' => 'Pixel 8',
        'platform' => 'android',
        'model' => 'Pixel 8',
        'os_version' => '14',
        'app_version' => '1.0.0',
    ];

    public function test_full_onboarding_flow_creates_active_account(): void
    {
        // Step 1: verify a known demo Service Number.
        $verify = $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '123456']);
        $verify->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('record.service_number', '123456')
            ->assertJsonPath('record.rank', 'Assistant Superintendent of Immigration');
        $verificationId = $verify->json('verification_id');

        // Step 2: confirm identity + phone -> OTP issued (exposed in test env).
        $confirm = $this->postJson('/api/v1/auth/confirm-identity', [
            'verification_id' => $verificationId,
            'phone' => '+2348030000000',
        ]);
        $confirm->assertOk()->assertJsonPath('otp_sent', true);
        $code = $confirm->json('debug_code');
        $this->assertNotEmpty($code);

        // Step 3: verify OTP.
        $this->postJson('/api/v1/auth/verify-otp', [
            'verification_id' => $verificationId,
            'code' => $code,
        ])->assertOk()->assertJsonPath('verified', true);

        // Step 4: set credentials + register device.
        $complete = $this->postJson('/api/v1/auth/set-credentials', [
            'verification_id' => $verificationId,
            'pin' => '1234',
            'device' => $this->device,
        ]);
        $complete->assertCreated()
            ->assertJsonPath('user.service_number', '123456')
            ->assertJsonStructure(['access_token', 'user' => ['id', 'display_name']]);

        $this->assertDatabaseHas('users', [
            'service_number' => '123456',
            'account_state' => User::STATE_ACTIVE,
        ]);
        $this->assertDatabaseHas('devices', ['name' => 'Pixel 8', 'platform' => 'android']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.created']);
    }

    public function test_leading_zeroes_are_preserved(): void
    {
        $verify = $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '001234']);
        $verify->assertOk()->assertJsonPath('verified', true)
            ->assertJsonPath('record.service_number', '001234');

        $id = $verify->json('verification_id');
        $code = $this->postJson('/api/v1/auth/confirm-identity', [
            'verification_id' => $id, 'phone' => '+2348030000001',
        ])->json('debug_code');
        $this->postJson('/api/v1/auth/verify-otp', ['verification_id' => $id, 'code' => $code]);
        $this->postJson('/api/v1/auth/set-credentials', [
            'verification_id' => $id, 'pin' => '4321', 'device' => $this->device,
        ])->assertCreated();

        // Stored exactly as "001234", never coerced to 1234.
        $this->assertDatabaseHas('users', ['service_number' => '001234']);
        $this->assertDatabaseMissing('users', ['service_number' => '1234']);
    }

    public function test_unknown_service_number_returns_generic_message(): void
    {
        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '000000'])
            ->assertOk()
            ->assertJsonPath('verified', false)
            ->assertJsonPath('message', 'We could not verify this Service Number. Please check the number and try again.')
            ->assertJsonMissingPath('verification_id');
    }

    public function test_retired_officer_cannot_onboard(): void
    {
        // 999999 exists in the demo source but is retired (not authorised).
        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '999999'])
            ->assertOk()
            ->assertJsonPath('verified', false);
    }

    #[DataProvider('invalidServiceNumbers')]
    public function test_non_numeric_service_numbers_are_rejected(string $value): void
    {
        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => $value])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('service_number');
    }

    public static function invalidServiceNumbers(): array
    {
        return [
            'slash prefix' => ['NIS/123456'],
            'dash' => ['NIS-123456'],
            'space' => ['NIS 123456'],
            'letters' => ['ABC123456'],
            'symbol' => ['12#456'],
            'too short' => ['12'],
        ];
    }

    public function test_wrong_otp_is_rejected_and_blocks_completion(): void
    {
        $verify = $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '654321']);
        $id = $verify->json('verification_id');
        $this->postJson('/api/v1/auth/confirm-identity', [
            'verification_id' => $id, 'phone' => '+2348030000002',
        ]);

        $this->postJson('/api/v1/auth/verify-otp', ['verification_id' => $id, 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('verified', false);

        // Completion is blocked because the phone was never verified.
        $this->postJson('/api/v1/auth/set-credentials', [
            'verification_id' => $id, 'pin' => '1234', 'device' => $this->device,
        ])->assertStatus(422);
    }
}
