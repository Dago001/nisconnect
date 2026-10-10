<?php

namespace Tests\Feature;

use App\Models\User;
use App\Personnel\PersonnelProviderInterface;
use App\Personnel\Providers\DemoPersonnelProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_list_includes_members_and_last_message_preview(): void
    {
        $a = User::factory()->create(['display_name' => 'Amina Bello']);
        User::factory()->create(['service_number' => '445566', 'display_name' => 'Chidi Okafor']);
        Sanctum::actingAs($a);

        $id = $this->postJson('/api/v1/chats', ['service_number' => '445566'])->json('data.id');
        $this->postJson("/api/v1/chats/{$id}/messages", ['type' => 'text', 'body' => 'Report at 0800.'])
            ->assertCreated();

        $this->getJson('/api/v1/chats')
            ->assertOk()
            ->assertJsonPath('data.0.last_message.body', 'Report at 0800.')
            ->assertJsonFragment(['display_name' => 'Chidi Okafor', 'service_number' => '445566']);
    }

    public function test_officer_can_change_pin_with_current_pin(): void
    {
        $user = User::factory()->create(['pin_hash' => Hash::make('1234')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/users/me/pin', ['current_pin' => '0000', 'new_pin' => '5678'])
            ->assertStatus(422)->assertJsonValidationErrors('current_pin');

        $this->putJson('/api/v1/users/me/pin', ['current_pin' => '1234', 'new_pin' => '5678'])
            ->assertOk();

        $this->assertTrue(Hash::check('5678', $user->fresh()->pin_hash));
    }

    public function test_new_pin_must_be_digits_and_different(): void
    {
        $user = User::factory()->create(['pin_hash' => Hash::make('1234')]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/users/me/pin', ['current_pin' => '1234', 'new_pin' => '12ab'])
            ->assertStatus(422)->assertJsonValidationErrors('new_pin');
        $this->putJson('/api/v1/users/me/pin', ['current_pin' => '1234', 'new_pin' => '1234'])
            ->assertStatus(422)->assertJsonValidationErrors('new_pin');
    }

    public function test_demo_provider_accepts_any_number_only_when_enabled(): void
    {
        $strict = new DemoPersonnelProvider('local');
        $this->assertNull($strict->findByServiceNumber('35562'));

        $open = new DemoPersonnelProvider('local', acceptAny: true);
        $record = $open->findByServiceNumber('35562');
        $this->assertNotNull($record);
        $this->assertSame('35562', $record->serviceNumber);
        $this->assertTrue($record->isAuthorised());

        // Known demo records are unchanged.
        $this->assertSame('Doe', $open->findByServiceNumber('123456')->surname);
    }

    public function test_accept_any_flag_reaches_onboarding(): void
    {
        config(['personnel.demo_accept_any' => true]);
        $this->app->forgetInstance(PersonnelProviderInterface::class);

        $this->postJson('/api/v1/auth/verify-service-number', ['service_number' => '35562'])
            ->assertOk()->assertJsonPath('verified', true);
    }
}
