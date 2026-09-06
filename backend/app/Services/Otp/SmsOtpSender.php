<?php

namespace App\Services\Otp;

use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Production OTP sender adapter. Wire to the approved NIS SMS gateway via env.
 * Endpoint/credentials are injected; no secrets live in code.
 */
class SmsOtpSender implements OtpSenderInterface
{
    /**
     * @param  array{endpoint: ?string, key: ?string, sender_id: ?string}  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {
    }

    public function send(string $phone, string $code): void
    {
        $endpoint = $this->config['endpoint'] ?? null;
        if (empty($endpoint)) {
            return; // Misconfiguration surfaces via monitoring; never leak to client.
        }

        $this->http
            ->withToken((string) ($this->config['key'] ?? ''))
            ->asJson()
            ->post($endpoint, [
                'to' => $phone,
                'from' => $this->config['sender_id'] ?? 'NISconnect',
                'message' => "Your NISconnect verification code is {$code}. It expires shortly. Do not share it.",
            ]);
    }
}
