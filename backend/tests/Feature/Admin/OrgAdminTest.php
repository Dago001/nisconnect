<?php

namespace Tests\Feature\Admin;

use App\Models\Command;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Formation;
use App\Models\Organisation;
use App\Models\Unit;
use App\Models\Zone;

class OrgAdminTest extends AdminTestCase
{
    public function test_every_tab_renders_and_unknown_type_404s(): void
    {
        $this->asAdmin();
        foreach (['directorates', 'departments', 'zones', 'commands', 'formations', 'units'] as $type) {
            $this->get(route('admin.org.index', ['type' => $type]))->assertOk()->assertSee('Organisation structure');
        }
        $this->get(route('admin.org.index', ['type' => 'users']))->assertNotFound();
        $this->post(route('admin.org.store', 'users'), ['name' => 'X'])->assertNotFound();
    }

    public function test_create_directorate_creates_organisation_when_missing(): void
    {
        $admin = $this->makeAdmin();
        $this->assertSame(0, Organisation::count());

        $this->asAdmin($admin)->post(route('admin.org.store', 'directorates'), ['name' => 'ICT/Cyber Security', 'code' => 'ICT'])
            ->assertRedirect(route('admin.org.index', ['type' => 'directorates']));

        $this->assertSame(1, Organisation::count());
        $dir = Directorate::where('code', 'ICT')->firstOrFail();
        $this->assertSame(Organisation::first()->id, $dir->organisation_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'org.created', 'resource_id' => $dir->id, 'actor_id' => $admin->id]);

        // A second one reuses the same organisation; duplicate code refused.
        $this->post(route('admin.org.store', 'zones'), ['name' => 'Zone A', 'code' => 'ZA']);
        $this->assertSame(1, Organisation::count());
        $this->post(route('admin.org.store', 'directorates'), ['name' => 'Other', 'code' => 'ICT'])->assertSessionHasErrors('code');
    }

    public function test_hierarchy_create_update_and_children_counts(): void
    {
        $this->asAdmin();
        $this->post(route('admin.org.store', 'zones'), ['name' => 'Zone A', 'code' => 'ZA']);
        $zone = Zone::firstOrFail();
        $this->post(route('admin.org.store', 'commands'), ['name' => 'FCT Command'])->assertSessionHasErrors('zone_id');
        $this->post(route('admin.org.store', 'commands'), ['name' => 'FCT Command', 'zone_id' => $zone->id])->assertSessionHasNoErrors();
        $command = Command::firstOrFail();
        $this->post(route('admin.org.store', 'formations'), ['name' => 'Nnamdi Azikiwe Airport', 'command_id' => $command->id, 'type' => 'airport']);
        $formation = Formation::firstOrFail();
        $this->assertSame('airport', $formation->type);
        $this->post(route('admin.org.store', 'units'), ['name' => 'Arrivals', 'formation_id' => $formation->id]);
        $this->assertSame(1, Unit::count());

        $this->get(route('admin.org.index', ['type' => 'commands']))->assertOk()->assertSee('FCT Command')->assertSee('Zone A');

        $zone2 = Zone::create(['organisation_id' => $zone->organisation_id, 'name' => 'Zone B', 'code' => 'ZB']);
        $this->put(route('admin.org.update', ['commands', $command->id]), ['name' => 'Abuja Command', 'code' => 'ABJ', 'zone_id' => $zone2->id])
            ->assertRedirect();
        $this->assertSame(['Abuja Command', 'ABJ', $zone2->id], array_values($command->fresh()->only(['name', 'code', 'zone_id'])));
        $this->assertDatabaseHas('audit_logs', ['action' => 'org.updated', 'resource_id' => $command->id]);
    }

    public function test_delete_refused_with_children_and_allowed_without(): void
    {
        $org = Organisation::create(['name' => 'NIS', 'code' => 'NIS']);
        $dir = Directorate::create(['organisation_id' => $org->id, 'name' => 'Finance', 'code' => 'FIN']);
        $dept = Department::create(['directorate_id' => $dir->id, 'name' => 'Payroll']);

        $this->asAdmin()->delete(route('admin.org.destroy', ['directorates', $dir->id]))->assertSessionHas('error');
        $this->assertNotNull($dir->fresh());

        $this->delete(route('admin.org.destroy', ['departments', $dept->id]))->assertSessionHas('status');
        $this->assertNull($dept->fresh());
        $this->delete(route('admin.org.destroy', ['directorates', $dir->id]))->assertSessionHas('status');
        $this->assertNull($dir->fresh());
        $this->assertDatabaseHas('audit_logs', ['action' => 'org.deleted', 'resource_id' => $dir->id]);

        $this->delete(route('admin.org.destroy', ['directorates', 'not-a-uuid']))->assertNotFound();
    }

    public function test_requires_org_permission(): void
    {
        $this->asAdmin($this->makeAdmin('directorate_admin'))->get(route('admin.org.index'))->assertForbidden();
    }
}
