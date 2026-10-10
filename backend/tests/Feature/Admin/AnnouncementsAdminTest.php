<?php

namespace Tests\Feature\Admin;

use App\Models\Announcement;
use App\Models\Device;
use App\Models\Notification;
use App\Models\PersonnelRecord;
use App\Models\PushToken;
use App\Models\User;
use App\Services\Push\PushMessage;
use App\Services\Push\PushSenderInterface;

class AnnouncementsAdminTest extends AdminTestCase
{
    /** @var list<array{token: string, message: PushMessage}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $sent = &$this->sent;
        $this->app->instance(PushSenderInterface::class, new class($sent) implements PushSenderInterface
        {
            public function __construct(private array &$sent) {}

            public function send(string $provider, string $token, PushMessage $message): void
            {
                $this->sent[] = ['token' => $token, 'message' => $message];
            }
        });
    }

    private function officerIn(string $command, array $state = []): User
    {
        $record = PersonnelRecord::factory()->create(['command' => $command]);

        return User::factory()->create(['personnel_record_id' => $record->id, 'service_number' => $record->service_number] + $state);
    }

    private function giveToken(User $user, string $token): void
    {
        $device = Device::create([
            'user_id' => $user->id, 'platform' => 'android',
            'name' => 'Test phone', 'status' => Device::STATUS_ACTIVE,
        ]);
        PushToken::create(['user_id' => $user->id, 'device_id' => $device->id, 'provider' => 'fcm', 'token' => $token]);
    }

    public function test_page_renders_with_audience_options(): void
    {
        $this->officerIn('Kano Command');
        $this->asAdmin()->get(route('admin.announcements.index'))
            ->assertOk()->assertSee('Announcements')->assertSee('Command: Kano Command')->assertSee('No announcements yet');
    }

    public function test_sends_to_command_audience_only_with_push(): void
    {
        $admin = $this->makeAdmin();
        $kano1 = $this->officerIn('Kano Command');
        $kano2 = $this->officerIn('Kano Command');
        $suspended = $this->officerIn('Kano Command', ['account_state' => User::STATE_SUSPENDED]);
        $lagos = $this->officerIn('Lagos Command');
        $this->giveToken($kano1, 'tok-kano-1');
        $this->giveToken($lagos, 'tok-lagos');

        $this->asAdmin($admin)->post(route('admin.announcements.store'), [
            'title' => 'Duty roster', 'body' => 'New roster from Monday.', 'priority' => 'urgent',
            'audience' => 'command|Kano Command',
        ])->assertRedirect(route('admin.announcements.index'))->assertSessionHas('status', 'Announcement sent to 2 officers.');

        $announcement = Announcement::firstOrFail();
        $this->assertSame(2, $announcement->recipients_count);
        $this->assertSame(['command', 'Kano Command', 'urgent', $admin->id],
            [$announcement->audience_type, $announcement->audience_value, $announcement->priority, $announcement->sent_by]);

        $this->assertEqualsCanonicalizing([$kano1->id, $kano2->id], Notification::where('type', 'announcement')->pluck('user_id')->all());
        $n = Notification::where('user_id', $kano1->id)->firstOrFail();
        $this->assertEquals(['announcement_id' => $announcement->id, 'priority' => 'urgent'], $n->data);
        $this->assertFalse(Notification::whereIn('user_id', [$suspended->id, $lagos->id])->exists());

        $this->assertSame(['tok-kano-1'], array_column($this->sent, 'token'));
        $this->assertSame('Duty roster', $this->sent[0]['message']->title);

        $this->assertDatabaseHas('audit_logs', ['action' => 'announcement.sent', 'resource_id' => $announcement->id, 'actor_id' => $admin->id]);

        // The officer app sees it in its notifications feed.
        $this->actingAs($kano1, 'sanctum')->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('data.0.type', 'announcement')->assertJsonPath('data.0.title', 'Duty roster');

        $this->actingAs($admin)->withSession(['admin_last_activity' => time()])
            ->get(route('admin.announcements.index'))->assertOk()->assertSee('Duty roster')->assertSee('Kano Command');
    }

    public function test_all_officers_audience(): void
    {
        $admin = $this->makeAdmin();
        User::factory()->count(3)->create();
        $this->asAdmin($admin)->post(route('admin.announcements.store'), [
            'title' => 'Hello', 'body' => 'Welcome to NISconnect.', 'priority' => 'normal', 'audience' => 'all',
        ])->assertSessionHas('status');
        $expected = User::where('account_state', 'active')->count();
        $this->assertSame($expected, Announcement::firstOrFail()->recipients_count);
        $this->assertSame($expected, Notification::count());
    }

    public function test_validation(): void
    {
        $this->asAdmin();
        $this->post(route('admin.announcements.store'), [
            'title' => 'X', 'body' => str_repeat('a', 2001), 'priority' => 'normal', 'audience' => 'all',
        ])->assertSessionHasErrors('body');
        $this->post(route('admin.announcements.store'), [
            'title' => 'X', 'body' => 'Y', 'priority' => 'normal', 'audience' => 'command|Nowhere',
        ])->assertSessionHasErrors('audience');
        $this->post(route('admin.announcements.store'), [
            'title' => 'X', 'body' => 'Y', 'priority' => 'loud', 'audience' => 'all',
        ])->assertSessionHasErrors('priority');
        $this->assertSame(0, Announcement::count());
    }

    public function test_requires_permission(): void
    {
        $this->asAdmin($this->makeAdmin('security_admin'))->get(route('admin.announcements.index'))->assertForbidden();
    }
}
