<?php

namespace App\Services\Push;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * Mints OAuth2 access tokens for a Google service account, for FCM's HTTP v1 API.
 *
 * FCM v1 does not take a long-lived server key: it wants a bearer token that
 * lives about an hour. Rather than have someone paste one in and watch pushes
 * go quiet the next day, this signs a JWT with the service account's private
 * key and exchanges it for an access token, caching it until shortly before it
 * expires.
 *
 * Only the private key stays on the server; nothing here reaches the client.
 */
class GoogleAccessToken
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Refresh this long before the real expiry, so a token never expires mid-send. */
    private const EXPIRY_MARGIN = 300;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly ?string $credentialsPath,
    ) {}

    public function isConfigured(): bool
    {
        return ! empty($this->credentialsPath) && is_readable($this->credentialsPath);
    }

    /**
     * A valid access token, from cache when possible.
     *
     * @throws RuntimeException when the credentials are missing or Google refuses them.
     */
    public function get(): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'FCM service-account credentials not configured; set FCM_CREDENTIALS to the JSON key path.'
            );
        }

        $credentials = $this->credentials();
        // Key the cache on the account, so rotating the key does not serve a stale token.
        $cacheKey = 'fcm.access_token.'.sha1($credentials['client_email']);

        $cached = $this->cache->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$token, $expiresIn] = $this->requestToken($credentials);

        $this->cache->put($cacheKey, $token, max(60, $expiresIn - self::EXPIRY_MARGIN));

        return $token;
    }

    /** @return array{client_email: string, private_key: string, token_uri: string, project_id: string} */
    private function credentials(): array
    {
        $raw = file_get_contents($this->credentialsPath);
        $data = json_decode((string) $raw, true);

        if (! is_array($data)) {
            throw new RuntimeException('FCM credentials file is not valid JSON: '.$this->credentialsPath);
        }

        foreach (['client_email', 'private_key', 'project_id'] as $required) {
            if (empty($data[$required])) {
                throw new RuntimeException("FCM credentials file is missing \"{$required}\".");
            }
        }

        return [
            'client_email' => $data['client_email'],
            'private_key' => $data['private_key'],
            'token_uri' => $data['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'project_id' => $data['project_id'],
        ];
    }

    /** The project id from the credentials, so the FCM endpoint need not be configured twice. */
    public function projectId(): ?string
    {
        return $this->isConfigured() ? $this->credentials()['project_id'] : null;
    }

    /**
     * @param  array{client_email: string, private_key: string, token_uri: string, project_id: string}  $credentials
     * @return array{0: string, 1: int} token and its lifetime in seconds
     */
    private function requestToken(array $credentials): array
    {
        $now = time();
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $jwt = $this->signJwt($claims, $credentials['private_key']);

        $response = $this->http->asForm()->post($credentials['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            // The body names the cause (clock skew, disabled account, revoked key)
            // and carries no secret, so it is safe and useful to surface.
            throw new RuntimeException(
                'Google refused the FCM token request: '.$response->status().' '.$response->body()
            );
        }

        $body = $response->json();
        if (empty($body['access_token'])) {
            throw new RuntimeException('Google returned no access_token for FCM.');
        }

        return [$body['access_token'], (int) ($body['expires_in'] ?? 3600)];
    }

    /** RS256-signed JWT. Implemented directly so the signing path stays auditable. */
    private function signJwt(array $claims, string $privateKey): string
    {
        $segments = [
            $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)),
            $this->base64Url(json_encode($claims, JSON_THROW_ON_ERROR)),
        ];

        $key = openssl_pkey_get_private($privateKey);
        if ($key === false) {
            throw new RuntimeException('FCM private key could not be read; is the JSON key intact?');
        }

        $signature = '';
        if (! openssl_sign(implode('.', $segments), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the FCM token request.');
        }

        $segments[] = $this->base64Url($signature);

        return implode('.', $segments);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
