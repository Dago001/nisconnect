<?php

namespace Tests\Feature;

use App\Models\BlockedUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_can_start_chat_and_exchange_messages(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create(['service_number' => '778899']);
        Sanctum::actingAs($a);

        // Start a direct chat by Service Number.
        $chat = $this->postJson('/api/v1/chats', ['service_number' => '778899']);
        $chat->assertCreated()->assertJsonPath('data.type', 'direct');
        $conversationId = $chat->json('data.id');

        // Send a message.
        $msg = $this->postJson("/api/v1/chats/{$conversationId}/messages", [
            'type' => 'text', 'body' => 'Good afternoon, officer.',
        ]);
        $msg->assertCreated()->assertJsonPath('data.body', 'Good afternoon, officer.')
            ->assertJsonPath('data.status', 'sent');
        $messageId = $msg->json('data.id');

        // Recipient reads it.
        Sanctum::actingAs($b);
        $this->getJson("/api/v1/chats/{$conversationId}/messages")
            ->assertOk()->assertJsonPath('data.0.body', 'Good afternoon, officer.');
        $this->postJson("/api/v1/messages/{$messageId}/read")->assertOk();

        $this->assertDatabaseHas('messages', ['id' => $messageId, 'status' => 'read']);
        $this->assertDatabaseHas('message_reads', ['message_id' => $messageId, 'user_id' => $b->id]);
    }

    public function test_starting_chat_twice_returns_same_conversation(): void
    {
        $a = User::factory()->create();
        User::factory()->create(['service_number' => '112233']);
        Sanctum::actingAs($a);

        $first = $this->postJson('/api/v1/chats', ['service_number' => '112233'])->json('data.id');
        $second = $this->postJson('/api/v1/chats', ['service_number' => '112233'])->json('data.id');

        $this->assertSame($first, $second);
    }

    public function test_non_member_cannot_read_conversation(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create(['service_number' => '445566']);
        $intruder = User::factory()->create();
        Sanctum::actingAs($a);
        $conversationId = $this->postJson('/api/v1/chats', ['service_number' => '445566'])->json('data.id');

        Sanctum::actingAs($intruder);
        $this->getJson("/api/v1/chats/{$conversationId}/messages")->assertForbidden();
    }

    public function test_blocked_officer_cannot_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create(['service_number' => '990011']);
        Sanctum::actingAs($a);
        $conversationId = $this->postJson('/api/v1/chats', ['service_number' => '990011'])->json('data.id');

        // B blocks A.
        BlockedUser::create(['blocker_id' => $b->id, 'blocked_id' => $a->id]);

        $this->postJson("/api/v1/chats/{$conversationId}/messages", ['type' => 'text', 'body' => 'hi'])
            ->assertStatus(422);
    }

    public function test_officer_can_create_group_and_members_can_message(): void
    {
        $owner = User::factory()->create();
        $m1 = User::factory()->create();
        Sanctum::actingAs($owner);

        $group = $this->postJson('/api/v1/groups', [
            'name' => 'Software & Data Department',
            'description' => 'Official department group',
            'members' => [$m1->id],
        ]);
        $group->assertCreated()->assertJsonPath('data.name', 'Software & Data Department');
        $conversationId = $group->json('data.conversation_id');

        $this->postJson("/api/v1/chats/{$conversationId}/messages", ['type' => 'text', 'body' => 'Welcome all.'])
            ->assertCreated();

        // Member can read.
        Sanctum::actingAs($m1);
        $this->getJson("/api/v1/chats/{$conversationId}/messages")
            ->assertOk()->assertJsonPath('data.0.body', 'Welcome all.');

        $this->assertDatabaseHas('groups', ['name' => 'Software & Data Department']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.created']);
    }

    public function test_only_group_admin_can_add_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        Sanctum::actingAs($owner);
        $group = $this->postJson('/api/v1/groups', ['name' => 'Ops', 'members' => [$member->id]]);
        $groupId = $group->json('data.id');

        // A plain member cannot add others.
        Sanctum::actingAs($member);
        $this->postJson("/api/v1/groups/{$groupId}/members", ['members' => [$outsider->id]])
            ->assertForbidden();

        // The owner can.
        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/groups/{$groupId}/members", ['members' => [$outsider->id]])
            ->assertOk()->assertJsonPath('added', 1);
    }

    public function test_media_upload_and_download_access_control(): void
    {
        \Illuminate\Support\Facades\Storage::fake('private');
        $a = User::factory()->create();
        $stranger = User::factory()->create();
        Sanctum::actingAs($a);

        $upload = $this->postJson('/api/v1/media', [
            'kind' => 'image',
            'file' => \Illuminate\Http\UploadedFile::fake()->image('photo.jpg', 100, 100),
        ]);
        $upload->assertCreated()->assertJsonStructure(['id', 'download_url']);
        $mediaId = $upload->json('id');

        // Owner can download.
        $this->get("/api/v1/media/{$mediaId}")->assertOk();

        // A stranger cannot.
        Sanctum::actingAs($stranger);
        $this->get("/api/v1/media/{$mediaId}")->assertForbidden();
    }
}
