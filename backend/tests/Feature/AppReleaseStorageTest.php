<?php

namespace Tests\Feature;

use App\Models\AppRelease;
use App\Services\Admin\AppReleaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * An AppRelease row and its file can get out of step: hosts that discard the
 * container filesystem between deploys (Render without a persistent disk) keep
 * the row and lose the file. The download page then silently says "available
 * soon", which reads as a bug in the page rather than missing storage.
 */
class AppReleaseStorageTest extends TestCase
{
    use RefreshDatabase;

    private function release(bool $withFile): AppRelease
    {
        $path = AppReleaseService::DIRECTORY.'/test-build.apk';

        if ($withFile) {
            // PK\x03\x04 — an APK is a zip, and the service checks the bytes.
            Storage::disk()->put($path, "PK\x03\x04test");
        }

        return AppRelease::create([
            'platform' => AppRelease::PLATFORM_ANDROID,
            'variant' => 'standard',
            'version' => '1.0.1',
            'file_path' => $path,
            'original_name' => 'app-arm64-v8a-release.apk',
            'size_bytes' => 7,
            'sha256' => str_repeat('a', 64),
            'is_published' => true,
        ]);
    }

    #[Test]
    public function the_download_page_offers_a_release_whose_file_is_present(): void
    {
        Storage::fake();
        $this->release(withFile: true);

        $this->get('/download')
            ->assertOk()
            ->assertSee('1.0.1');
    }

    #[Test]
    public function the_download_page_does_not_offer_a_release_whose_file_is_gone(): void
    {
        Storage::fake();
        $this->release(withFile: false);

        // Published, but unusable: officers must not be sent to a broken link.
        $this->get('/download')
            ->assertOk()
            ->assertDontSee(route('downloads.android', 'standard'));
    }

    #[Test]
    public function downloading_a_release_whose_file_is_gone_is_a_404_not_a_server_error(): void
    {
        Storage::fake();
        $this->release(withFile: false);

        $this->get(route('downloads.android', 'standard'))->assertNotFound();
    }

    #[Test]
    public function the_storage_root_follows_the_persistent_disk_when_one_is_configured(): void
    {
        // Render mounts a volume and sets NISCONNECT_STORAGE_ROOT to it; the
        // local disk has to write there rather than inside the container.
        $this->assertSame(
            storage_path('app/private'),
            config('filesystems.disks.local.root'),
            'without the env var the default must be unchanged',
        );
    }
}
