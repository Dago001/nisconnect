<?php

namespace App\Services\Push;

use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Production push adapter. Routes to FCM (Android) or APNs (iOS) HTTP endpoints
 * using credentials from config/services.php. Endpoints/keys are env-only; no
 * secrets in code. Kept behind PushSenderInterface so providers can change
 * without touching callers.
 */
class HttpPushSender implements PushSenderInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {
    }

    public function send(string $provider, string $token, PushMessage $message): void
    {
        $provider === 'apns' ? $this->sendApns($token, $message) : $this->sendFcm($token, $message);
    }

    private function sendFcm(string $token, PushMessage $message): void
    {
        $endpoint = $this->config['fcm']['endpoint'] ?? null;
        $key = $this->config['fcm']['key'] ?? null;
        if (empty($endpoint) || empty($key)) {
            return;
        }
        $this->http->withToken($key)->asJson()->post($endpoint, [
            'message' => [
                'token' => $token,
                'notification' => ['title' => $message->title, 'body' => $message->body],
                'data' => $message->data,
            ],
        ]);
    }

    private function sendApns(string $token, PushMessage $message): void
    {
        $endpoint = $this->config['apns']['endpoint'] ?? null;
        $key = $this->config['apns']['key'] ?? null;
        if (empty($endpoint) || empty($key)) {
            return;
        }
        $this->http->withToken($key)->asJson()->post(rtrim($endpoint, '/').'/'.$token, [
            'aps' => ['alert' => ['title' => $message->title, 'body' => $message->body]],
            'data' => $message->data,
        ]);
    }
}
