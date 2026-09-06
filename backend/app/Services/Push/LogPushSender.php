<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;

/** Development push sender — logs instead of delivering. */
class LogPushSender implements PushSenderInterface
{
    public function send(string $provider, string $token, PushMessage $message): void
    {
        Log::info('NISconnect push (dev)', [
            'provider' => $provider,
            'token' => substr($token, 0, 8).'…',
            'title' => $message->title,
        ]);
    }
}
