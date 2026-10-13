<?php

namespace Tests\Unit;

use App\Services\Push\GoogleAccessToken;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleAccessTokenTest extends TestCase
{
    private function writeKey(array $overrides = []): string
    {
        // A throwaway RSA key committed as a fixture. It signs nothing real and
        // grants nothing — generating one per run instead needs an openssl.cnf
        // that PHP cannot find on a default Windows install.
        $pem = file_get_contents(__DIR__.'/../fixtures/test-signing-key.pem');

        $payload = array_merge([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key' => $pem,
            'client_email' => 'svc@test-project.iam.gserviceaccount.com',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], $overrides);

        $path = tempnam(sys_get_temp_dir(), 'fcm').'.json';
        file_put_contents($path, json_encode($payload));

        return $path;
    }

    private function cache(): Repository
    {
        return new Repository(new ArrayStore);
    }

    #[Test]
    public function it_is_not_configured_without_a_credentials_path(): void
    {
        $svc = new GoogleAccessToken(new HttpFactory, $this->cache(), null);

        $this->assertFalse($svc->isConfigured());
        $this->assertNull($svc->projectId());
    }

    #[Test]
    public function it_is_not_configured_when_the_file_is_missing(): void
    {
        $svc = new GoogleAccessToken(new HttpFactory, $this->cache(), 'C:/nope/missing.json');

        $this->assertFalse($svc->isConfigured());
    }

    #[Test]
    public function it_reads_the_project_id_from_the_key(): void
    {
        $path = $this->writeKey();
        $svc = new GoogleAccessToken(new HttpFactory, $this->cache(), $path);

        $this->assertTrue($svc->isConfigured());
        $this->assertSame('test-project', $svc->projectId());

        unlink($path);
    }

    #[Test]
    public function it_exchanges_a_signed_jwt_for_an_access_token(): void
    {
        $path = $this->writeKey();
        $http = new HttpFactory;
        $http->fake([
            'oauth2.googleapis.com/*' => $http->response(
                ['access_token' => 'ya29.test-token', 'expires_in' => 3600],
            ),
        ]);

        $svc = new GoogleAccessToken($http, $this->cache(), $path);

        $this->assertSame('ya29.test-token', $svc->get());

        // The assertion must be a real RS256 JWT, not a placeholder.
        $http->assertSent(function ($request) {
            $jwt = $request['assertion'] ?? '';
            $parts = explode('.', $jwt);

            return $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer'
                && count($parts) === 3
                && str_contains(base64_decode(strtr($parts[0], '-_', '+/')), 'RS256');
        });

        unlink($path);
    }

    #[Test]
    public function it_caches_the_token_rather_than_refetching(): void
    {
        $path = $this->writeKey();
        $http = new HttpFactory;
        $http->fake([
            'oauth2.googleapis.com/*' => $http->response(
                ['access_token' => 'ya29.cached', 'expires_in' => 3600],
            ),
        ]);

        $svc = new GoogleAccessToken($http, $this->cache(), $path);
        $svc->get();
        $svc->get();
        $svc->get();

        $http->assertSentCount(1);

        unlink($path);
    }

    #[Test]
    public function it_raises_a_clear_error_when_google_refuses(): void
    {
        $path = $this->writeKey();
        $http = new HttpFactory;
        $http->fake([
            'oauth2.googleapis.com/*' => $http->response(['error' => 'invalid_grant'], 400),
        ]);

        $svc = new GoogleAccessToken($http, $this->cache(), $path);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Google refused.*invalid_grant/s');

        try {
            $svc->get();
        } finally {
            unlink($path);
        }
    }
}
