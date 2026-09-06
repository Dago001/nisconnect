<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChannelSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_publisher_can_post_to_channel(): void
    {
        $publisher = User::factory()->create();
        $subscriber = User::factory()->create();
        $channel = Channel::create(['name' => 'HQ Announcements', 'created_by' => $publisher->id]);
        ChannelMember::create(['channel_id' => $channel->id, 'user_id' => $publisher->id, 'role' => 'publisher']);

        // Subscriber cannot publish.
        Sanctum::actingAs($subscriber);
        $this->postJson("/api/v1/channels/{$channel->id}/posts", ['body' => 'x'])->assertForbidden();

        // Publisher can.
        Sanctum::actingAs($publisher);
        $this->postJson("/api/v1/channels/{$channel->id}/posts", ['body' => 'Service-wide notice.'])
            ->assertCreated();

        // Anyone can read posts.
        Sanctum::actingAs($subscriber);
        $this->getJson("/api/v1/channels/{$channel->id}/posts")
            ->assertOk()->assertJsonPath('data.0.body', 'Service-wide notice.');

        $this->assertDatabaseHas('audit_logs', ['action' => 'channel.published']);
    }

    public function test_officer_can_subscribe_to_channel(): void
    {
        $u = User::factory()->create();
        $channel = Channel::create(['name' => 'Command Announcements', 'created_by' => $u->id]);

        Sanctum::actingAs($u);
        $this->postJson("/api/v1/channels/{$channel->id}/subscribe")->assertOk();
        $this->assertDatabaseHas('channel_members', ['channel_id' => $channel->id, 'user_id' => $u->id]);
    }

    public function test_block_unblock_and_report(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/safety/block', ['user_id' => $other->id])->assertOk();
        $this->assertDatabaseHas('blocked_users', ['blocker_id' => $me->id, 'blocked_id' => $other->id]);

        $this->postJson('/api/v1/safety/unblock', ['user_id' => $other->id])->assertOk();
        $this->assertDatabaseMissing('blocked_users', ['blocker_id' => $me->id, 'blocked_id' => $other->id]);

        $this->postJson('/api/v1/safety/report', [
            'target_type' => 'user', 'target_id' => $other->id, 'reason' => 'Impersonation',
        ])->assertCreated();
        $this->assertDatabaseHas('reports', ['reporter_id' => $me->id, 'reason' => 'Impersonation']);
    }

    public function test_cannot_block_self(): void
    {
        $me = User::factory()->create();
        Sanctum::actingAs($me);
        $this->postJson('/api/v1/safety/block', ['user_id' => $me->id])->assertStatus(422);
    }

    public function test_notifications_list_and_mark_read(): void
    {
        $me = User::factory()->create();
        $n = Notification::create([
            'user_id' => $me->id, 'type' => 'security', 'title' => 'New device signed in',
        ]);
        Sanctum::actingAs($me);

        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonPath('data.0.title', 'New device signed in');

        $this->postJson("/api/v1/notifications/{$n->id}/read")->assertOk();
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('unread', 0);
    }
}
