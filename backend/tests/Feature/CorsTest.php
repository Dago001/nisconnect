<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The web client is hosted on its own origin (Netlify, a CDN, a separate
 * domain), so the API has to name that origin for the browser. A mistake here
 * only shows up as "blocked by CORS policy" in a browser console — never in
 * the API's own logs — so it is pinned down here instead.
 */
class CorsTest extends TestCase
{
    private const ORIGIN = 'https://nisconnect.netlify.app';

    private function allow(string $origins): void
    {
        config(['cors.allowed_origins' => array_filter(array_map('trim', explode(',', $origins)))]);
    }

    #[Test]
    public function an_allowed_origin_may_call_the_api(): void
    {
        $this->allow(self::ORIGIN);

        $this->get('/api/v1/meta', ['Origin' => self::ORIGIN])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', self::ORIGIN);
    }

    #[Test]
    public function a_preflight_from_an_allowed_origin_succeeds(): void
    {
        $this->allow(self::ORIGIN);

        $this->call('OPTIONS', '/api/v1/meta', [], [], [], [
            'HTTP_ORIGIN' => self::ORIGIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertNoContent(204)
            ->assertHeader('Access-Control-Allow-Origin', self::ORIGIN);
    }

    #[Test]
    public function an_unlisted_origin_is_not_granted_access(): void
    {
        $this->allow(self::ORIGIN);

        $response = $this->get('/api/v1/meta', ['Origin' => 'https://attacker.example']);

        // Laravel answers the request but withholds the header, so the
        // browser refuses to hand the body to the calling page.
        $this->assertNotSame(
            'https://attacker.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    #[Test]
    public function no_origins_are_allowed_by_default(): void
    {
        // A single-origin deployment should not hand out CORS access at all.
        $this->allow('');

        $response = $this->get('/api/v1/meta', ['Origin' => self::ORIGIN]);

        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function credentials_are_not_enabled(): void
    {
        // The app sends a bearer token; allowing credentialed cross-origin
        // requests would widen the blast radius of a misconfigured origin.
        $this->assertFalse(config('cors.supports_credentials'));
    }

    #[Test]
    public function the_broadcasting_auth_endpoint_is_covered(): void
    {
        // Private channel subscription is authorised cross-origin too, so it
        // must be in the CORS paths or live chat silently fails on the web.
        $this->assertContains('broadcasting/auth', config('cors.paths'));
    }
}
