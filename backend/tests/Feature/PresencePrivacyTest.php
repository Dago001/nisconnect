<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PresencePrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_can_update_presence(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/users/me/presence', ['presence' => 'online'])
            ->assertOk()->assertJsonPath('presence', 'online');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'presence' => 'online']);
    }

    public function test_directory_hides_presence_when_privacy_is_nobody(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create([
            'service_number' => '515151',
            'presence' => 'online',
            'privacy' => array_merge(User::defaultPrivacy(), ['online' => 'nobody', 'last_seen' => 'nobody']),
        ]);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/directory/515151')
            ->assertOk()
            ->assertJsonPath('data.presence', null)
            ->assertJsonPath('data.last_seen_at', null);
    }

    public function test_directory_shows_presence_when_privacy_is_everyone(): void
    {
        $viewer = User::factory()->create();
        User::factory()->create([
            'service_number' => '525252',
            'presence' => 'online',
            'privacy' => User::defaultPrivacy(),
        ]);
        Sanctum::actingAs($viewer);

        $this->getJson('/api/v1/directory/525252')
            ->assertOk()->assertJsonPath('data.presence', 'online');
    }

    public function test_blocked_users_list(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['display_name' => 'Blocked Officer']);
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/safety/block', ['user_id' => $other->id])->assertOk();
        $this->getJson('/api/v1/safety/blocked')
            ->assertOk()
            ->assertJsonPath('data.0.display_name', 'Blocked Officer');
    }
}
