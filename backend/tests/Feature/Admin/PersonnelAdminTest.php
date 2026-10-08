<?php

namespace Tests\Feature\Admin;

use App\Models\PersonnelRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class PersonnelAdminTest extends AdminTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'service_number' => '000777', 'surname' => 'Bello', 'first_name' => 'Amina', 'other_name' => '',
            'rank' => 'Inspector of Immigration', 'directorate' => 'Border Management', 'department' => '',
            'zone' => 'Zone B', 'command' => 'Kano Command', 'formation' => '', 'unit' => '', 'posting' => '',
            'official_email' => 'a.bello@example.gov.ng', 'status' => 'active',
        ], $overrides);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('personnel.csv', $content);
    }

    public function test_index_lists_filters_and_shows_provider(): void
    {
        PersonnelRecord::factory()->create(['surname' => 'Zubairu', 'service_number' => '004321', 'status' => 'retired']);
        PersonnelRecord::factory()->create(['surname' => 'Okonkwo', 'status' => 'active']);
        $officer = User::factory()->create();

        $this->asAdmin()->get(route('admin.personnel.index'))
            ->assertOk()->assertSee('Personnel records')->assertSee('Zubairu')->assertSee('Demo data');

        $this->get(route('admin.personnel.index', ['q' => '0043']))->assertOk()->assertSee('Zubairu')->assertDontSee('Okonkwo');
        $this->get(route('admin.personnel.index', ['status' => 'retired']))->assertSee('Zubairu')->assertDontSee('Okonkwo');
        $this->get(route('admin.personnel.index', ['registered' => 'yes']))
            ->assertSee($officer->personnelRecord->surname)->assertDontSee('Zubairu');
        $this->get(route('admin.personnel.index', ['registered' => 'no']))
            ->assertSee('Zubairu')->assertDontSee($officer->personnelRecord->surname);
    }

    public function test_export_streams_filtered_csv(): void
    {
        PersonnelRecord::factory()->create(['surname' => 'Zubairu', 'service_number' => '004321', 'status' => 'retired']);
        PersonnelRecord::factory()->create(['surname' => 'Okonkwo']);

        $csv = $this->asAdmin()->get(route('admin.personnel.export', ['status' => 'retired']))->assertOk()->streamedContent();
        $this->assertStringContainsString('004321', $csv);
        $this->assertStringNotContainsString('Okonkwo', $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'personnel.exported']);
    }

    public function test_create_and_store_record(): void
    {
        $admin = $this->makeAdmin();
        $this->asAdmin($admin)->get(route('admin.personnel.create'))->assertOk()->assertSee('Add personnel record');

        $this->post(route('admin.personnel.store'), $this->payload())->assertRedirect();
        $record = PersonnelRecord::where('service_number', '000777')->firstOrFail();
        $this->assertSame('admin', $record->source);
        $this->assertDatabaseHas('audit_logs', ['action' => 'personnel.created', 'actor_id' => $admin->id, 'resource_id' => $record->id]);
    }

    public function test_store_validates_service_number_and_uniqueness(): void
    {
        PersonnelRecord::factory()->create(['service_number' => '000777']);
        $this->asAdmin();
        $this->post(route('admin.personnel.store'), $this->payload(['service_number' => '12a45']))
            ->assertSessionHasErrors(['service_number' => 'A Service Number may contain digits only (up to 20).']);
        $this->post(route('admin.personnel.store'), $this->payload())
            ->assertSessionHasErrors(['service_number' => 'A personnel record with this Service Number already exists.']);
        $this->post(route('admin.personnel.store'), $this->payload(['service_number' => '1', 'status' => 'gone']))
            ->assertSessionHasErrors('status');
    }

    public function test_service_number_locked_once_account_exists(): void
    {
        $officer = User::factory()->create();
        $record = $officer->personnelRecord;

        $this->asAdmin()->get(route('admin.personnel.edit', $record))->assertOk()->assertSee('Locked');
        $this->put(route('admin.personnel.update', $record), $this->payload(['service_number' => '999111']))
            ->assertSessionHasErrors('service_number');
        $this->assertSame($officer->service_number, $record->fresh()->service_number);
    }

    public function test_status_change_suspends_active_account_and_revokes_tokens(): void
    {
        $admin = $this->makeAdmin();
        $officer = User::factory()->create();
        $officer->createToken('app');
        $record = $officer->personnelRecord;

        $this->asAdmin($admin)->put(route('admin.personnel.update', $record), $this->payload([
            'service_number' => $record->service_number, 'status' => 'retired',
        ]))->assertRedirect(route('admin.personnel.edit', $record))->assertSessionHas('status');

        $this->assertSame('retired', $record->fresh()->status);
        $this->assertSame(User::STATE_SUSPENDED, $officer->fresh()->account_state);
        $this->assertSame(0, $officer->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'officer.suspended', 'resource_id' => $officer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'personnel.updated', 'resource_id' => $record->id]);
    }

    public function test_cannot_retire_own_record(): void
    {
        $admin = $this->makeAdmin();
        $record = $admin->personnelRecord;
        $this->asAdmin($admin)->put(route('admin.personnel.update', $record), $this->payload([
            'service_number' => $record->service_number, 'status' => 'dismissed',
        ]))->assertSessionHasErrors('status');
        $this->assertSame(User::STATE_ACTIVE, $admin->fresh()->account_state);
    }

    public function test_import_page_and_template(): void
    {
        $this->asAdmin()->get(route('admin.personnel.import'))->assertOk()->assertSee('Import personnel');
        $csv = $this->get(route('admin.personnel.import.template'))->assertOk()->streamedContent();
        $this->assertStringContainsString('service_number,surname,first_name,other_name,rank,directorate', $csv);
    }

    public function test_import_dry_run_writes_nothing(): void
    {
        PersonnelRecord::factory()->create(['service_number' => '000123', 'surname' => 'Old']);
        $csv = "service_number,surname,first_name,status\n000123,New,Ada,active\n000456,Musa,Ibrahim,\nabc,Bad,Row,active\n000789,,NoSurname,active\n";

        $this->asAdmin()->post(route('admin.personnel.import.store'), ['file' => $this->csv($csv), 'dry_run' => '1'])
            ->assertRedirect(route('admin.personnel.import'));
        $result = session('import_result');
        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['create']);
        $this->assertSame(1, $result['update']);
        $this->assertSame(2, $result['error_count']);
        $this->assertSame([4, 5], array_column($result['errors'], 'line'));

        $this->assertSame('Old', PersonnelRecord::where('service_number', '000123')->value('surname'));
        $this->assertFalse(PersonnelRecord::where('service_number', '000456')->exists());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'personnel.imported']);

        $this->get(route('admin.personnel.import'))->assertOk()->assertSee('Dry run preview')->assertSee('000456');
    }

    public function test_import_upserts_keeps_leading_zeroes_and_audits(): void
    {
        $officer = User::factory()->create();
        PersonnelRecord::factory()->create(['service_number' => '000123', 'surname' => 'Old']);
        $csv = "\xEF\xBB\xBFservice_number,surname,first_name,rank,status\n"
            ."000123,New,Ada,ASI,active\n0000456,Musa,Ibrahim,,\n"
            ."{$officer->service_number},{$officer->personnelRecord->surname},{$officer->personnelRecord->first_name},,suspended\n"
            ."000456,Dup,Row,,\n000456,Dup,Again,,\n";

        $this->asAdmin()->post(route('admin.personnel.import.store'), ['file' => $this->csv($csv)])
            ->assertRedirect(route('admin.personnel.import'))->assertSessionHas('status');

        $this->assertSame('New', PersonnelRecord::where('service_number', '000123')->value('surname'));
        $created = PersonnelRecord::where('service_number', '0000456')->firstOrFail();
        $this->assertSame('admin', $created->source);
        $this->assertSame('active', $created->status);
        $this->assertTrue(PersonnelRecord::where('service_number', '000456')->exists());
        $this->assertSame(User::STATE_SUSPENDED, $officer->fresh()->account_state);

        $result = session('import_result');
        $this->assertSame(['created' => 2, 'updated' => 2], ['created' => $result['created'], 'updated' => $result['updated']]);
        $this->assertSame(1, $result['error_count']); // duplicate in file
        $this->assertDatabaseHas('audit_logs', ['action' => 'personnel.imported']);
    }

    public function test_import_rejects_missing_headers_and_large_files(): void
    {
        $this->asAdmin()->post(route('admin.personnel.import.store'), ['file' => $this->csv("name,rank\nA,B\n"), 'dry_run' => '1']);
        $this->assertStringContainsString('Missing required column', session('import_result')['errors'][0]['message']);

        $big = UploadedFile::fake()->create('big.csv', 6000, 'text/csv');
        $this->post(route('admin.personnel.import.store'), ['file' => $big])->assertSessionHasErrors('file');
    }

    public function test_permissions_are_enforced(): void
    {
        $limited = $this->makeAdmin('directorate_admin'); // personnel.view only
        $this->asAdmin($limited)->get(route('admin.personnel.index'))->assertOk();
        $this->get(route('admin.personnel.create'))->assertForbidden();
        $this->get(route('admin.personnel.import'))->assertForbidden();
    }
}
