<?php

namespace App\Services\Push;

/**
 * Maps a notification type to the Android channel and the alert sound used for
 * it on each platform.
 *
 * Android ties the sound to the channel, and a channel's sound is fixed once
 * the app has created it — changing it later needs a NEW channel id, which is
 * why the ids below carry a version suffix. iOS takes the sound per message.
 *
 * Sound files ship with the clients:
 *   Android  mobile/android/app/src/main/res/raw/<name>.wav   (no extension in the id)
 *   iOS      mobile/ios/Runner/Sounds/<name>.caf              (extension required)
 */
final class NotificationChannels
{
    /** Bump the suffix when a channel's sound or importance changes. */
    private const VERSION = 'v1';

    /**
     * @var array<string, array{channel: string, sound: string, importance: string}>
     */
    private const MAP = [
        // An incoming call must cut through: full-volume ringtone, max importance.
        'call' => ['channel' => 'calls', 'sound' => 'nis_ring', 'importance' => 'high'],
        // The signature NISconnect message tone.
        'message' => ['channel' => 'messages', 'sound' => 'nis_alert', 'importance' => 'high'],
        // Official channel posts and service-wide announcements.
        'announcement' => ['channel' => 'announcements', 'sound' => 'nis_notice', 'importance' => 'high'],
        // Security events (new device, PIN change) — distinct and attention-getting.
        'security' => ['channel' => 'security', 'sound' => 'nis_notice', 'importance' => 'high'],
    ];

    private const FALLBACK = ['channel' => 'general', 'sound' => 'nis_alert', 'importance' => 'default'];

    /** @return array{channel: string, sound: string, importance: string} */
    public static function for(string $type): array
    {
        // Types are namespaced like "call.incoming" or "message.new"; match the stem.
        $stem = explode('.', $type)[0];

        return self::MAP[$stem] ?? self::FALLBACK;
    }

    /** The versioned Android channel id the client must create with the same name. */
    public static function channelId(string $type): string
    {
        return self::for($type)['channel'].'_'.self::VERSION;
    }

    /** Android sound resource name in res/raw, without a file extension. */
    public static function androidSound(string $type): string
    {
        return self::for($type)['sound'];
    }

    /** iOS sound file name, extension included. */
    public static function iosSound(string $type): string
    {
        return self::for($type)['sound'].'.caf';
    }
}
