<?php

namespace Tests\Feature;

use App\Models\PersonnelRecord;
use App\Services\Otp\NullOtpSender;
use App\Services\Otp\OtpSenderInterface;
use App\Services\Otp\TermiiOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OtpDeliveryOptionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function startOnboarding(string $phone): array
    {
        $v = $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '123456'])->assertOk();

        return $this->postJson('/api/v1/auth/confirm-identity', [
            'verification_id' => $v->json('verification_id'),
            'phone' => $phone,
        ])->assertOk()->json();
    }

    public function test_sign_up_code_is_shown_in_production_only_when_enabled(): void
    {
        // The Render setup: officers authorised by the admin-imported
        // personnel_records table, read through the database provider.
        config(['personnel.provider' => 'database', 'personnel.database.connection' => config('database.default'),
            'personnel.database.table' => 'personnel_records']);
        PersonnelRecord::create(['service_number' => '123456', 'surname' => 'Bello', 'first_name' => 'Ada',
            'status' => 'active', 'source' => 'admin']);
        $this->app['env'] = 'production';
        config(['otp.expose_in_response' => true, 'otp.onboarding_show_code' => false, 'otp.driver' => 'none']);
        $this->app->forgetInstance(OtpSenderInterface::class);
        $this->assertArrayNotHasKey('debug_code', $this->startOnboarding('08031234567'));

        config(['otp.onboarding_show_code' => true]);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->startOnboarding('08039876543')['debug_code']);
    }

    public function test_none_driver_sends_nothing(): void
    {
        config(['otp.driver' => 'none']);
        $this->app->forgetInstance(OtpSenderInterface::class);
        $this->assertInstanceOf(NullOtpSender::class, $this->app->make(OtpSenderInterface::class));
    }

    public function test_termii_driver_posts_international_number(): void
    {
        Http::fake(['*' => Http::response(['code' => 'ok'])]);
        config(['otp.driver' => 'termii', 'services.termii.key' => 'test-key', 'services.termii.sender_id' => 'NISconnect']);
        $this->app->forgetInstance(OtpSenderInterface::class);
        $sender = $this->app->make(OtpSenderInterface::class);
        $this->assertInstanceOf(TermiiOtpSender::class, $sender);

        $sender->send('0803 123 4567', '123456');

        Http::assertSent(fn ($r) => $r->url() === 'https://v3.api.termii.com/api/sms/send'
            && $r['to'] === '2348031234567'
            && $r['api_key'] === 'test-key'
            && $r['channel'] === 'dnd'
            && str_contains($r['sms'], '123456'));
    }

    public function test_termii_without_key_sends_nothing(): void
    {
        Http::fake();
        (new TermiiOtpSender(app(HttpFactory::class), ['base_url' => null, 'key' => '', 'sender_id' => null, 'channel' => null]))
            ->send('08031234567', '123456');
        Http::assertNothingSent();
    }
}
