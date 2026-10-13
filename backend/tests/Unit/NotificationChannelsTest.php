<?php

namespace Tests\Unit;

use App\Services\Push\NotificationChannels;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The channel ids and sound names here are a contract with the Flutter client
 * (lib/services/push/notification_channels.dart). Android resolves the tone
 * from the channel, so a mismatch silently downgrades alerts to the stock
 * sound rather than failing loudly — hence pinning the exact strings.
 */
class NotificationChannelsTest extends TestCase
{
    #[Test]
    public function calls_ring_on_their_own_channel(): void
    {
        $this->assertSame('calls_v1', NotificationChannels::channelId('call.incoming'));
        $this->assertSame('nis_ring', NotificationChannels::androidSound('call.incoming'));
        $this->assertSame('nis_ring.caf', NotificationChannels::iosSound('call.incoming'));
    }

    #[Test]
    public function messages_use_the_alert_tone(): void
    {
        $this->assertSame('messages_v1', NotificationChannels::channelId('message'));
        $this->assertSame('nis_alert', NotificationChannels::androidSound('message'));
    }

    #[Test]
    public function announcements_and_security_share_the_notice_tone(): void
    {
        $this->assertSame('announcements_v1', NotificationChannels::channelId('announcement'));
        $this->assertSame('security_v1', NotificationChannels::channelId('security'));
        $this->assertSame('nis_notice', NotificationChannels::androidSound('announcement'));
        $this->assertSame('nis_notice', NotificationChannels::androidSound('security'));
    }

    #[Test]
    public function a_namespaced_type_matches_on_its_stem(): void
    {
        $this->assertSame(
            NotificationChannels::channelId('message'),
            NotificationChannels::channelId('message.new'),
        );
    }

    #[Test]
    public function an_unknown_type_falls_back_rather_than_failing(): void
    {
        $this->assertSame('general_v1', NotificationChannels::channelId('something.unmapped'));
        $this->assertSame('general_v1', NotificationChannels::channelId(''));
    }
}
