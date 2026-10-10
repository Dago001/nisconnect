<?php

namespace Tests\Feature\Admin;

use App\Models\AppRelease;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AppDownloadsAdminTest extends AdminTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function fakeApk(string $name = 'app-release.apk', string $body = 'fake apk body'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "PK\x03\x04".$body);
    }

    public function test_requires_permission(): void
    {
        foreach (['nis_admin', 'security_admin'] as $role) {
            $admin = $this->makeAdmin($role);
            $this->asAdmin($admin)->get(route('admin.downloads.index'))->assertForbidden();
            $this->asAdmin($admin)->post(route('admin.downloads.store'), [
                'variant' => 'standard', 'version' => '1.0.0', 'file' => $this->fakeApk(),
            ])->assertForbidden();
        }
        $this->assertSame(0, AppRelease::count());
    }

    public function test_page_renders_and_appears_in_sidebar(): void
    {
        $this->asAdmin()->get(route('admin.downloads.index'))
            ->assertOk()->assertSee('App downloads')->assertSee('No app uploaded yet')->assertSee('Upload Android app');
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('App downloads');
    }

    public function test_upload_stores_file_with_random_name_and_audits(): void
    {
        $admin = $this->makeAdmin();
        $file = $this->fakeApk('../../evil name.apk');
        $expectedSha = hash('sha256', "PK\x03\x04fake apk body");

        $this->asAdmin($admin)->post(route('admin.downloads.store'), [
            'variant' => 'standard', 'version' => '1.0.0+5', 'notes' => 'First release', 'file' => $file,
        ])->assertRedirect(route('admin.downloads.index'))->assertSessionHas('status');

        $release = AppRelease::firstOrFail();
        $this->assertSame(['android', 'standard', '1.0.0+5', 'First release', $expectedSha, 17, true, $admin->id], [
            $release->platform, $release->variant, $release->version, $release->notes,
            $release->sha256, $release->size_bytes, $release->is_published, $release->uploaded_by,
        ]);
        $this->assertStringStartsWith('app-releases/', $release->file_path);
        $this->assertStringNotContainsString('evil', $release->file_path);
        Storage::assertExists($release->file_path);
        $this->assertSame("PK\x03\x04fake apk body", Storage::get($release->file_path));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'app_release.uploaded', 'resource_id' => $release->id, 'actor_id' => $admin->id,
        ]);

        $this->asAdmin($admin)->get(route('admin.downloads.index'))
            ->assertOk()->assertSee('Version 1.0.0+5')->assertSee(substr($expectedSha, 0, 10))->assertSee('Offered now');
    }

    public function test_rejects_files_that_are_not_apks(): void
    {
        $this->asAdmin();
        // Right extension, wrong bytes.
        $this->post(route('admin.downloads.store'), [
            'variant' => 'standard', 'version' => '1.0.0',
            'file' => UploadedFile::fake()->createWithContent('app.apk', '<?php echo "hi";'),
        ])->assertSessionHasErrors('file');
        // ZIP bytes, wrong extension.
        $this->post(route('admin.downloads.store'), [
            'variant' => 'standard', 'version' => '1.0.0', 'file' => $this->fakeApk('app.zip'),
        ])->assertSessionHasErrors('file');
        // Too big.
        $this->post(route('admin.downloads.store'), [
            'variant' => 'standard', 'version' => '1.0.0',
            'file' => UploadedFile::fake()->create('app.apk', 204801),
        ])->assertSessionHasErrors(['file' => 'The app file is too big. The limit is 200 MB.']);
        // Bad version / variant.
        $this->post(route('admin.downloads.store'), [
            'variant' => 'tablet', 'version' => '1.0 beta', 'file' => $this->fakeApk(),
        ])->assertSessionHasErrors(['variant', 'version']);

        $this->assertSame(0, AppRelease::count());
        $this->assertSame([], Storage::allFiles());
    }

    public function test_publish_toggle_and_audit(): void
    {
        $admin = $this->makeAdmin();
        $this->asAdmin($admin)->post(route('admin.downloads.store'), [
            'variant' => 'legacy', 'version' => '1.0.0', 'file' => $this->fakeApk(),
        ]);
        $release = AppRelease::firstOrFail();

        $this->post(route('admin.downloads.toggle', $release))->assertRedirect(route('admin.downloads.index'));
        $this->assertFalse($release->fresh()->is_published);
        $this->assertDatabaseHas('audit_logs', ['action' => 'app_release.unpublished', 'resource_id' => $release->id, 'actor_id' => $admin->id]);

        $this->post(route('admin.downloads.toggle', $release));
        $this->assertTrue($release->fresh()->is_published);
        $this->assertDatabaseHas('audit_logs', ['action' => 'app_release.published', 'resource_id' => $release->id]);
    }

    public function test_delete_removes_record_and_file(): void
    {
        $admin = $this->makeAdmin();
        $this->asAdmin($admin)->post(route('admin.downloads.store'), [
            'variant' => 'standard', 'version' => '2.0.0', 'file' => $this->fakeApk(),
        ]);
        $release = AppRelease::firstOrFail();
        Storage::assertExists($release->file_path);

        $this->delete(route('admin.downloads.destroy', $release))->assertRedirect(route('admin.downloads.index'));

        $this->assertSame(0, AppRelease::count());
        Storage::assertMissing($release->file_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'app_release.deleted', 'resource_id' => $release->id, 'actor_id' => $admin->id]);
    }
}
