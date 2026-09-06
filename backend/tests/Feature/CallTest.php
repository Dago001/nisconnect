<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Calls\LiveKitTokenService;
use App\Services\Messaging\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallTest extends TestCase
{
    use RefreshDatabase;

    private function directConversation(User $a, User $b): string
    {
        return app(ConversationService::class)->directConversation($a, $b)->id;
    }

    public function test_officer_can_initiate_a_call_and_get_a_livekit_token(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversationId = $this->directConversation($a, $b);

        Sanctum::actingAs($a);
        $res = $this->postJson('/api/v1/calls', [
            'conversation_id' => $conversationId, 'type' => 'video',
        ]);

        $res->assertCreated()
            ->assertJsonPath('call.type', 'video')
            ->assertJsonPath('call.status', 'ringing')
            ->assertJsonStructure(['call' => ['id', 'room'], 'token', 'livekit_url']);

        $this->assertDatabaseHas('calls', ['type' => 'video', 'initiator_id' => $a->id]);
        $this->assertDatabaseHas('call_participants', ['user_id' => $b->id, 'state' => 'ringing']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'call.initiated']);
    }

    public function test_minted_token_is_a_verifiable_jwt(): void
    {
        $svc = app(LiveKitTokenService::class);
        $token = $svc->mint(room: 'call_x', identity: 'user-1', name: 'Officer A');

        [$h, $p, $s] = explode('.', $token);
        $expected = rtrim(strtr(base64_encode(
            hash_hmac('sha256', "$h.$p", 'devsecret0123456789abcdef', true)
        ), '+/', '-_'), '=');
        $this->assertSame($expected, $s, 'JWT signature must verify with the API secret.');

        $payload = json_decode(base64_decode(strtr($p, '-_', '+/')), true);
        $this->assertSame('call_x', $payload['video']['room']);
        $this->assertTrue($payload['video']['roomJoin']);
    }

    public function test_callee_can_answer_and_participants_can_get_tokens(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversationId = $this->directConversation($a, $b);

        Sanctum::actingAs($a);
        $callId = $this->postJson('/api/v1/calls', ['conversation_id' => $conversationId, 'type' => 'voice'])
            ->json('call.id');

        Sanctum::actingAs($b);
        $this->postJson("/api/v1/calls/{$callId}/answer")->assertOk()->assertJsonPath('call.status', 'connected');
        $this->getJson("/api/v1/calls/{$callId}/token")->assertOk()->assertJsonStructure(['token', 'room']);
    }

    public function test_non_participant_cannot_get_a_token(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $intruder = User::factory()->create();
        $conversationId = $this->directConversation($a, $b);

        Sanctum::actingAs($a);
        $callId = $this->postJson('/api/v1/calls', ['conversation_id' => $conversationId, 'type' => 'voice'])
            ->json('call.id');

        Sanctum::actingAs($intruder);
        $this->getJson("/api/v1/calls/{$callId}/token")->assertForbidden();
    }

    public function test_initiator_can_end_call(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $conversationId = $this->directConversation($a, $b);

        Sanctum::actingAs($a);
        $callId = $this->postJson('/api/v1/calls', ['conversation_id' => $conversationId, 'type' => 'voice'])
            ->json('call.id');
        $this->postJson("/api/v1/calls/{$callId}/end")->assertOk();

        $this->assertDatabaseHas('calls', ['id' => $callId, 'status' => 'ended']);
    }
}
