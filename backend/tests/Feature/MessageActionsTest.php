<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\User;
use App\Services\Messaging\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessageActionsTest extends TestCase
{
    use RefreshDatabase;

    private function directChat(User $a, User $b): string
    {
        return app(ConversationService::class)->directConversation($a, $b)->id;
    }

    public function test_sender_can_edit_own_text_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = $this->directChat($a, $b);
        Sanctum::actingAs($a);
        $id = $this->postJson("/api/v1/chats/{$c}/messages", ['type' => 'text', 'body' => 'origional'])->json('data.id');

        $this->patchJson("/api/v1/messages/{$id}", ['body' => 'corrected'])
            ->assertOk()->assertJsonPath('data.body', 'corrected');
        $this->assertDatabaseHas('messages', ['id' => $id, 'body' => 'corrected']);
        $this->assertNotNull(Message::find($id)->edited_at);
    }

    public function test_cannot_edit_another_users_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = $this->directChat($a, $b);
        Sanctum::actingAs($a);
        $id = $this->postJson("/api/v1/chats/{$c}/messages", ['type' => 'text', 'body' => 'mine'])->json('data.id');

        Sanctum::actingAs($b);
        $this->patchJson("/api/v1/messages/{$id}", ['body' => 'hacked'])->assertStatus(422);
    }

    public function test_member_can_pin_and_unpin(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $c = $this->directChat($a, $b);
        Sanctum::actingAs($a);
        $id = $this->postJson("/api/v1/chats/{$c}/messages", ['type' => 'text', 'body' => 'important'])->json('data.id');

        $this->postJson("/api/v1/messages/{$id}/pin", ['pinned' => true])->assertOk();
        $this->assertNotNull(Message::find($id)->pinned_at);
        $this->postJson("/api/v1/messages/{$id}/pin", ['pinned' => false])->assertOk();
        $this->assertNull(Message::find($id)->pinned_at);
    }

    public function test_forward_message_to_another_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $d = User::factory()->create();
        $chat1 = $this->directChat($a, $b);
        $chat2 = $this->directChat($a, $d);
        Sanctum::actingAs($a);
        $srcId = $this->postJson("/api/v1/chats/{$chat1}/messages", ['type' => 'text', 'body' => 'briefing note'])->json('data.id');

        $res = $this->postJson("/api/v1/messages/{$srcId}/forward", ['conversation_id' => $chat2]);
        $res->assertCreated()->assertJsonPath('data.body', 'briefing note');
        $this->assertSame($srcId, Message::find($res->json('data.id'))->forwarded_from_id);
    }
}
