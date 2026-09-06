<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Messaging\ConversationService;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSenderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_a_push_token_binds_it_to_the_device(): void
    {
        $user = User::factory()->create();

        // Log in to obtain a device-bound token.
        $login = $this->postJson('/api/v1/auth/login', [
            'service_number' => $user->service_number,
            'pin' => '1234',
            'device' => ['name' => 'Pixel', 'platform' => 'android'],
        ])->json('access_token');

        $this->withToken($login)->postJson('/api/v1/devices/push-token', [
            'provider' => 'fcm', 'token' => 'fcm-abc-123',
        ])->assertOk();

        $this->assertDatabaseHas('push_tokens', [
            'user_id' => $user->id, 'provider' => 'fcm', 'token' => 'fcm-abc-123',
        ]);
    }

    public function test_sending_a_message_notifies_and_pushes_to_the_recipient(): void
    {
        // Capture push deliveries.
        $sent = [];
        $this->app->instance(PushSenderInterface::class, new class($sent) implements PushSenderInterface {
            public function __construct(public array &$sent) {}
            public function send(string $provider, string $token, PushMessage $message): void
            {
                $this->sent[] = ['provider' => $provider, 'token' => $token, 'title' => $message->title];
            }
        });

        $a = User::factory()->create(['display_name' => 'ASI John Doe']);
        $b = User::factory()->create();
        \App\Models\PushToken::create([
            'user_id' => $b->id,
            'device_id' => \App\Models\Device::create([
                'user_id' => $b->id, 'name' => 'B phone', 'platform' => 'ios',
                'status' => 'active', 'last_active_at' => now(),
            ])->id,
            'provider' => 'apns', 'token' => 'apns-b-token',
        ]);

        $conversation = app(ConversationService::class)->directConversation($a, $b);

        Sanctum::actingAs($a);
        $this->postJson("/api/v1/chats/{$conversation->id}/messages", [
            'type' => 'text', 'body' => 'Report to HQ at 0900.',
        ])->assertCreated();

        // In-app notification persisted for the recipient only.
        $this->assertDatabaseHas('notifications', ['user_id' => $b->id, 'type' => 'message', 'title' => 'ASI John Doe']);
        $this->assertDatabaseMissing('notifications', ['user_id' => $a->id]);

        // Push delivered to the recipient's token.
        $this->assertCount(1, $sent);
        $this->assertSame('apns-b-token', $sent[0]['token']);
        $this->assertSame('ASI John Doe', $sent[0]['title']);
    }
}
