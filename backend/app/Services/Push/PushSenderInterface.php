<?php

namespace App\Services\Push;

interface PushSenderInterface
{
    /** Deliver a push to a single device token via its provider (fcm|apns). */
    public function send(string $provider, string $token, PushMessage $message): void;
}
