<?php

namespace App\Services\Calls;

/**
 * Mints short-lived LiveKit access tokens (JWT, HS256) on the server.
 *
 * The LiveKit API secret never leaves the backend and is never sent to the
 * mobile client — only the resulting signed token is. Implemented without an
 * external JWT dependency so the signing path is auditable.
 */
class LiveKitTokenService
{
    /**
     * @param  array{host: ?string, api_key: ?string, api_secret: ?string}  $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['api_key']) && ! empty($this->config['api_secret']);
    }

    /**
     * Build a join token for a participant on a room.
     *
     * @param  bool  $canPublish  Whether the participant may publish media.
     * @param  int  $ttlSeconds  Token lifetime.
     */
    public function mint(
        string $room,
        string $identity,
        string $name,
        bool $canPublish = true,
        int $ttlSeconds = 3600,
    ): string {
        $now = time();

        $payload = [
            'iss' => $this->config['api_key'],
            'sub' => $identity,
            'name' => $name,
            'nbf' => $now,
            'iat' => $now,
            'exp' => $now + $ttlSeconds,
            'video' => [
                'room' => $room,
                'roomJoin' => true,
                'canPublish' => $canPublish,
                'canSubscribe' => true,
                'canPublishData' => true,
            ],
        ];

        return $this->encode($payload, (string) $this->config['api_secret']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload, string $secret): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
