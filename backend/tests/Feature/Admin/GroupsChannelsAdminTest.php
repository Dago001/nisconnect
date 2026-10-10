<?php

namespace Tests\Feature\Admin;

use App\Models\Channel;
use App\Models\ChannelMember;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\Admin\GroupChannelAdminService;

class GroupsChannelsAdminTest extends AdminTestCase
{
    public function test_group_index_search_and_type_filter(): void
    {
        $admin = $this->makeAdmin();
        app(GroupChannelAdminService::class)->createOrganisationalGroup('Kano Command Ops', null, $admin);
        $conv = Conversation::create(['type' => 'group']);
        Group::create(['conversation_id' => $conv->id, 'name' => 'Lunch Club', 'type' => Group::TYPE_STANDARD]);

        $this->asAdmin($admin)->get(route('admin.groups.index'))->assertOk()->assertSee('Kano Command Ops')->assertSee('Lunch Club');
        $this->get(route('admin.groups.index', ['q' => 'kano']))->assertSee('Kano Command Ops')->assertDontSee('Lunch Club');
        $this->get(route('admin.groups.index', ['type' => 'standard']))->assertSee('Lunch Club')->assertDontSee('Kano Command Ops');
    }

    public function test_create_organisational_group_with_conversation(): void
    {
        $admin = $this->makeAdmin();
        $this->asAdmin($admin)->post(route('admin.groups.store'), ['name' => 'Border Patrol', 'description' => 'North-west'])
            ->assertRedirect();
        $group = Group::where('name', 'Border Patrol')->firstOrFail();
        $this->assertSame(Group::TYPE_ORGANISATIONAL, $group->type);
        $this->assertSame($group->id, $group->conversation->group_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.created', 'resource_id' => $group->id, 'actor_id' => $admin->id]);
        $this->get(route('admin.groups.show', $group))->assertOk()->assertSee('Border Patrol')->assertSee('No members yet');
    }

    public function test_group_members_add_role_change_remove(): void
    {
        $admin = $this->makeAdmin();
        $group = app(GroupChannelAdminService::class)->createOrganisationalGroup('Ops', null, $admin);
        $officer = User::factory()->create(['service_number' => '001234', 'personnel_record_id' => null]);

        $this->asAdmin($admin)->post(route('admin.groups.members.store', $group), ['service_number' => '1234', 'role' => 'member'])
            ->assertSessionHasErrors('service_number');
        $this->post(route('admin.groups.members.store', $group), ['service_number' => '001234', 'role' => 'member'])
            ->assertSessionHas('status');
        $this->assertDatabaseHas('group_members', ['group_id' => $group->id, 'user_id' => $officer->id, 'role' => 'member', 'added_by' => $admin->id]);
        $this->assertTrue($group->conversation->hasMember($officer->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.member_added', 'resource_id' => $group->id]);

        $this->post(route('admin.groups.members.store', $group), ['service_number' => '001234', 'role' => 'admin']);
        $this->assertSame('admin', GroupMember::where('user_id', $officer->id)->value('role'));
        $this->assertSame('admin', ConversationMember::where('user_id', $officer->id)->value('role'));
        $this->get(route('admin.groups.show', $group))->assertOk()->assertSee($officer->display_name)->assertSee('001234');

        $this->delete(route('admin.groups.members.destroy', [$group, $officer]))->assertSessionHas('status');
        $this->assertDatabaseMissing('group_members', ['group_id' => $group->id, 'user_id' => $officer->id]);
        $this->assertFalse($group->conversation->hasMember($officer->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.member_removed', 'resource_id' => $group->id]);

        $suspended = User::factory()->suspended()->create();
        $this->post(route('admin.groups.members.store', $group), ['service_number' => $suspended->service_number, 'role' => 'member'])
            ->assertSessionHasErrors('service_number');
    }

    public function test_group_update_and_delete(): void
    {
        $admin = $this->makeAdmin();
        $svc = app(GroupChannelAdminService::class);
        $group = $svc->createOrganisationalGroup('Ops', null, $admin);
        $officer = User::factory()->create();
        $svc->addGroupMember($group, $officer, 'member', $admin);

        $this->asAdmin($admin)->put(route('admin.groups.update', $group), ['name' => 'Ops Room', 'description' => 'Updated'])
            ->assertSessionHas('status');
        $this->assertSame('Ops Room', $group->fresh()->name);
        $this->assertSame('Ops Room', $group->conversation->fresh()->title);
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.updated', 'resource_id' => $group->id]);

        $this->delete(route('admin.groups.destroy', $group))->assertRedirect(route('admin.groups.index'));
        $this->assertSoftDeleted('groups', ['id' => $group->id]);
        $this->assertFalse($group->conversation->hasMember($officer->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'group.deleted', 'resource_id' => $group->id]);
    }

    public function test_channel_lifecycle(): void
    {
        $admin = $this->makeAdmin();
        $this->asAdmin($admin)->get(route('admin.channels.index'))->assertOk()->assertSee('Official channels');

        $this->post(route('admin.channels.store'), ['name' => 'CG Briefings', 'description' => 'From the CGI'])->assertRedirect();
        $channel = Channel::where('name', 'CG Briefings')->firstOrFail();
        $this->assertDatabaseHas('channel_members', ['channel_id' => $channel->id, 'user_id' => $admin->id, 'role' => 'publisher']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'channel.created', 'resource_id' => $channel->id]);
        $this->get(route('admin.channels.index', ['q' => 'brief']))->assertSee('CG Briefings');

        $officer = User::factory()->create();
        $this->post(route('admin.channels.members.store', $channel), ['service_number' => $officer->service_number, 'role' => 'subscriber'])
            ->assertSessionHas('status');
        $this->assertSame('subscriber', ChannelMember::where('user_id', $officer->id)->value('role'));
        $this->post(route('admin.channels.members.store', $channel), ['service_number' => $officer->service_number, 'role' => 'publisher']);
        $this->assertSame('publisher', ChannelMember::where('user_id', $officer->id)->value('role'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'channel.member_role_changed', 'resource_id' => $channel->id]);
        $this->post(route('admin.channels.members.store', $channel), ['service_number' => $officer->service_number, 'role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->get(route('admin.channels.show', $channel))->assertOk()->assertSee($officer->display_name)->assertSee('Publisher');

        $this->put(route('admin.channels.update', $channel), ['name' => 'CGI Briefings', 'description' => ''])->assertSessionHas('status');
        $this->assertSame('CGI Briefings', $channel->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'channel.updated', 'resource_id' => $channel->id]);

        $this->delete(route('admin.channels.members.destroy', [$channel, $officer]))->assertSessionHas('status');
        $this->assertDatabaseMissing('channel_members', ['channel_id' => $channel->id, 'user_id' => $officer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'channel.member_removed', 'resource_id' => $channel->id]);

        $this->delete(route('admin.channels.destroy', $channel))->assertRedirect(route('admin.channels.index'));
        $this->assertSoftDeleted('channels', ['id' => $channel->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'channel.deleted', 'resource_id' => $channel->id]);
    }

    public function test_group_admin_cannot_manage_channels(): void
    {
        $this->asAdmin($this->makeAdmin('group_admin'))->get(route('admin.groups.index'))->assertOk();
        $this->get(route('admin.channels.index'))->assertForbidden();
    }
}
