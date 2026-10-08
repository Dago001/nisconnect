<?php

namespace Tests\Feature\Admin;

use App\Models\Conversation;
use App\Models\Group;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;

class ReportsAdminTest extends AdminTestCase
{
    private function report(User $reporter, string $type, string $targetId, array $attrs = []): Report
    {
        return Report::create(array_merge([
            'reporter_id' => $reporter->id, 'target_type' => $type, 'target_id' => $targetId,
            'reason' => 'Harassment', 'status' => 'open',
        ], $attrs));
    }

    private function message(User $sender, string $body = 'Confidential operational detail'): Message
    {
        $conversation = Conversation::create(['type' => 'group', 'title' => 'Ops Room', 'created_by' => $sender->id]);

        return Message::create(['conversation_id' => $conversation->id, 'sender_id' => $sender->id, 'type' => 'text', 'body' => $body]);
    }

    public function test_index_lists_reports_with_tabs_and_resolved_targets_without_message_content(): void
    {
        $reporter = User::factory()->create(['display_name' => 'Ada Reporter']);
        $offender = User::factory()->create(['display_name' => 'Bola Offender', 'service_number' => '004321']);
        $msg = $this->message($offender);
        $group = Group::create(['conversation_id' => $msg->conversation_id, 'name' => 'Lagos Watch']);

        $this->report($reporter, 'user', $offender->id, ['reason' => 'Abusive language']);
        $this->report($reporter, 'message', $msg->id, ['reason' => 'Leaked info']);
        $this->report($reporter, 'group', $group->id, ['reason' => 'Spam group']);
        $this->report($reporter, 'user', $offender->id, ['status' => 'dismissed', 'reason' => 'Old dismissed thing']);

        $this->asAdmin()->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Ada Reporter')
            ->assertSee('Bola Offender')
            ->assertSee('004321')
            ->assertSee('Message in a group conversation')
            ->assertSee('Lagos Watch')
            ->assertSee('Abusive language')
            ->assertDontSee('Old dismissed thing')
            ->assertDontSee('Confidential operational detail');
    }

    public function test_filters_by_target_type_reason_and_status(): void
    {
        $admin = $this->makeAdmin();
        $reporter = User::factory()->create();
        $offender = User::factory()->create();
        $msg = $this->message($offender);
        $this->report($reporter, 'user', $offender->id, ['reason' => 'Abusive language']);
        $this->report($reporter, 'message', $msg->id, ['reason' => 'Leaked info']);
        $this->report($reporter, 'user', $offender->id, ['status' => 'dismissed', 'reason' => 'Old dismissed thing']);

        $this->asAdmin($admin)->get(route('admin.reports.index', ['target_type' => 'message']))
            ->assertOk()->assertSee('Leaked info')->assertDontSee('Abusive language');
        $this->asAdmin($admin)->get(route('admin.reports.index', ['q' => 'abusive']))
            ->assertOk()->assertSee('Abusive language')->assertDontSee('Leaked info');
        $this->asAdmin($admin)->get(route('admin.reports.index', ['status' => 'all']))
            ->assertOk()->assertSee('Old dismissed thing')->assertSee('Leaked info');
        $this->asAdmin($admin)->get(route('admin.reports.index', ['status' => 'bogus']))
            ->assertSessionHasErrors('status');
    }

    public function test_show_page_reveals_message_body_only_on_detail_with_warning(): void
    {
        $reporter = User::factory()->create();
        $offender = User::factory()->create();
        $msg = $this->message($offender, 'The secret meeting is at noon');
        $report = $this->report($reporter, 'message', $msg->id, ['details' => 'Shared restricted info']);
        $this->report(User::factory()->create(), 'message', $msg->id, ['reason' => 'Second complaint']);

        $this->asAdmin()->get(route('admin.reports.show', $report))
            ->assertOk()
            ->assertSee('Reported message content (confidential)')
            ->assertSee('The secret meeting is at noon')
            ->assertSee('Shared restricted info')
            ->assertSee('Second complaint');
    }

    public function test_action_updates_status_note_and_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $report = $this->report(User::factory()->create(), 'user', User::factory()->create()->id);

        $this->asAdmin($admin)->post(route('admin.reports.action', $report), [
            'status' => 'dismissed', 'resolution_note' => 'Banter between colleagues, no action.',
        ])->assertRedirect(route('admin.reports.show', $report))->assertSessionHas('status');

        $report->refresh();
        $this->assertSame('dismissed', $report->status);
        $this->assertSame('Banter between colleagues, no action.', $report->resolution_note);
        $this->assertSame($admin->id, $report->reviewed_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.reviewed', 'actor_id' => $admin->id, 'resource_id' => $report->id]);

        $this->asAdmin($admin)->get(route('admin.reports.show', $report))
            ->assertOk()->assertSee('Open → Dismissed')->assertSee('Banter between colleagues');

        $this->asAdmin($admin)->post(route('admin.reports.action', $report), ['status' => 'open'])
            ->assertSessionHasErrors('status');
    }

    public function test_action_can_suspend_reported_officer(): void
    {
        $admin = $this->makeAdmin('security_admin');
        $offender = User::factory()->create();
        $offender->createToken('phone', ['officer']);
        $report = $this->report(User::factory()->create(), 'user', $offender->id);

        $this->asAdmin($admin)->get(route('admin.reports.show', $report))->assertOk()->assertSee('Suspend the reported officer');

        $this->asAdmin($admin)->post(route('admin.reports.action', $report), [
            'status' => 'actioned', 'suspend_officer' => '1',
        ])->assertRedirect();

        $this->assertSame(User::STATE_SUSPENDED, $offender->fresh()->account_state);
        $this->assertSame(0, $offender->tokens()->count());
        $this->assertDatabaseHas('security_events', ['event' => 'account_suspended', 'user_id' => $offender->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'officer.suspended', 'resource_id' => $offender->id, 'actor_id' => $admin->id]);
    }

    public function test_suspend_requires_officers_manage_and_cannot_target_self(): void
    {
        $moderator = $this->makeAdmin('directorate_admin'); // reports.review but not officers.manage
        $offender = User::factory()->create();
        $report = $this->report(User::factory()->create(), 'user', $offender->id);

        $this->asAdmin($moderator)->get(route('admin.reports.show', $report))
            ->assertOk()->assertDontSee('Suspend the reported officer');
        $this->asAdmin($moderator)->post(route('admin.reports.action', $report), [
            'status' => 'actioned', 'suspend_officer' => '1',
        ])->assertForbidden();
        $this->assertSame(User::STATE_ACTIVE, $offender->fresh()->account_state);

        $super = $this->makeAdmin();
        $selfReport = $this->report($offender, 'user', $super->id);
        $this->asAdmin($super)->post(route('admin.reports.action', $selfReport), [
            'status' => 'actioned', 'suspend_officer' => '1',
        ])->assertRedirect();
        $this->assertSame(User::STATE_ACTIVE, $super->fresh()->account_state);
    }

    public function test_admin_without_permission_is_refused(): void
    {
        $report = $this->report(User::factory()->create(), 'user', User::factory()->create()->id);
        $groupAdmin = $this->makeAdmin('group_admin');

        $this->asAdmin($groupAdmin)->get(route('admin.reports.index'))->assertForbidden();
        $this->asAdmin($groupAdmin)->get(route('admin.reports.show', $report))->assertForbidden();
        $this->asAdmin($groupAdmin)->post(route('admin.reports.action', $report), ['status' => 'dismissed'])->assertForbidden();
    }
}
