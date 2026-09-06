<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Security-focused tests: enumeration resistance, rate limiting, IDOR / broken
 * access control, authentication bypass and injection safety.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('verify');
        parent::tearDown();
    }

    public function test_service_number_verify_is_rate_limited(): void
    {
        // The 'verify' limiter allows 8/min by IP; the 9th is throttled.
        $status = 200;
        for ($i = 0; $i < 12; $i++) {
            $status = $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '123456'])
                ->getStatusCode();
            if ($status === 429) {
                break;
            }
        }
        $this->assertSame(429, $status, 'Verification endpoint must throttle to resist enumeration.');
    }

    public function test_unknown_and_unauthorised_numbers_are_indistinguishable(): void
    {
        $unknown = $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '000000'])->json();
        $retired = $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '999999'])->json();

        // Same shape and message — no signal that 999999 exists but is retired.
        $this->assertFalse($unknown['verified']);
        $this->assertFalse($retired['verified']);
        $this->assertSame($unknown['message'], $retired['message']);
        $this->assertArrayNotHasKey('verification_id', $unknown);
        $this->assertArrayNotHasKey('verification_id', $retired);
    }

    public function test_protected_endpoints_reject_unauthenticated_requests(): void
    {
        foreach ([
            ['get', '/api/v1/users/me'],
            ['get', '/api/v1/chats'],
            ['get', '/api/v1/directory/search?q=a'],
            ['get', '/api/v1/devices'],
            ['get', '/api/v1/notifications'],
        ] as [$method, $path]) {
            $this->json($method, $path)->assertUnauthorized();
        }
    }

    public function test_idor_cannot_read_another_users_notifications(): void
    {
        $victim = User::factory()->create();
        $attacker = User::factory()->create();
        $n = Notification::create(['user_id' => $victim->id, 'type' => 'security', 'title' => 'private']);

        Sanctum::actingAs($attacker);
        $this->postJson("/api/v1/notifications/{$n->id}/read")->assertForbidden();
        $this->assertDatabaseHas('notifications', ['id' => $n->id, 'read_at' => null]);
    }

    public function test_injection_style_service_number_is_rejected_by_validation(): void
    {
        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '1;DROP TABLE users;--'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('service_number');

        // Table intact.
        $this->assertSame(0, User::count());
    }

    public function test_token_is_revoked_on_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('t', ['officer'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        // The token row is destroyed — the real revocation property.
        $this->assertSame(0, PersonalAccessToken::count());

        // Force the auth guard to re-resolve (a fresh HTTP process would);
        // the deleted token can no longer authenticate.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/users/me')->assertUnauthorized();
    }
}
