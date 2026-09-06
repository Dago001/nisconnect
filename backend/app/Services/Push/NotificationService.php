<?php

namespace App\Services\Push;

use App\Models\Notification;
use App\Models\PushToken;
use App\Models\User;

/**
 * Persists in-app notifications and fans them out to a user's registered push
 * tokens. Respects the recipient's notification-relevant privacy where set.
 */
class NotificationService
{
    public function __construct(private readonly PushSenderInterface $push) {}

    /**
     * @param  array<string, string>  $data
     */
    public function notify(User $user, string $type, string $title, ?string $body = null, array $data = []): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
        ]);

        $message = new PushMessage($title, $body ?? '', array_merge($data, ['type' => $type]));

        PushToken::where('user_id', $user->id)->get()->each(function (PushToken $t) use ($message) {
            $this->push->send($t->provider, $t->token, $message);
        });

        return $notification;
    }
}
