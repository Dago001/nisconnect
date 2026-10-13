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
    ) {}

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

        $type = $message->data['type'] ?? '';
        $isCall = str_starts_with($type, 'call');

        $this->http->withToken($key)->asJson()->post($endpoint, [
            'message' => [
                'token' => $token,
                'notification' => ['title' => $message->title, 'body' => $message->body],
                'data' => $message->data,
                'android' => [
                    // Calls must wake the device immediately; everything else is
                    // normal priority so it can be batched to save battery.
                    'priority' => $isCall ? 'HIGH' : 'NORMAL',
                    'notification' => [
                        // The sound follows the channel on Android 8+, but older
                        // releases read this field, so send both.
                        'channel_id' => NotificationChannels::channelId($type),
                        'sound' => NotificationChannels::androidSound($type),
                        'default_vibrate_timings' => ! $isCall,
                    ],
                ],
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

        $type = $message->data['type'] ?? '';
        $isCall = str_starts_with($type, 'call');

        $this->http
            ->withToken($key)
            // Calls are time-sensitive: 10 lets them break through Focus modes.
            ->withHeaders(['apns-priority' => $isCall ? '10' : '5'])
            ->asJson()
            ->post(rtrim($endpoint, '/').'/'.$token, [
                'aps' => [
                    'alert' => ['title' => $message->title, 'body' => $message->body],
                    'sound' => $isCall
                        // critical=1 rings even on silent, but needs Apple's
                        // Critical Alerts entitlement; without it iOS falls back
                        // to the plain sound rather than dropping the alert.
                        ? ['critical' => 1, 'name' => NotificationChannels::iosSound($type), 'volume' => 1.0]
                        : NotificationChannels::iosSound($type),
                    'interruption-level' => $isCall ? 'time-sensitive' : 'active',
                ],
                'data' => $message->data,
            ]);
    }
}
