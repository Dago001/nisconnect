<?php

namespace Tests\Feature;

use App\Models\AppRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function release(string $variant, string $version, array $attributes = []): AppRelease
    {
        $bytes = "PK\x03\x04".$variant.$version;
        $path = 'app-releases/'.$variant.'-'.$version.'.apk';
        Storage::put($path, $bytes);

        $release = AppRelease::create($attributes + [
            'platform' => 'android', 'variant' => $variant, 'version' => $version,
            'file_path' => $path, 'original_name' => 'app-release.apk',
            'size_bytes' => strlen($bytes), 'sha256' => hash('sha256', $bytes),
        ]);
        if (isset($attributes['created_at'])) {
            $release->forceFill(['created_at' => $attributes['created_at']])->save();
        }

        return $release;
    }

    public function test_empty_state_is_public(): void
    {
        $this->get(route('downloads.index'))
            ->assertOk()
            ->assertSee('Get NISconnect')
            ->assertSee('The Android app will be available here soon')
            ->assertSee('Add to Home Screen')
            ->assertSee('Open the web app')
            ->assertDontSee('Download for Android')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_populated_state_shows_newest_published_builds(): void
    {
        $this->release('standard', '1.0.0', ['created_at' => now()->subDays(3)]);
        $new = $this->release('standard', '1.1.0', ['created_at' => now()->subDay()]);
        $this->release('standard', '9.9.9', ['is_published' => false]);
        $this->release('legacy', '1.1.0');

        $this->get(route('downloads.index'))
            ->assertOk()
            ->assertSee('Download for Android')
            ->assertSee('1.1.0')
            ->assertSee($new->sha256)
            ->assertSee('Older phone? Download the 32-bit version')
            ->assertDontSee('9.9.9')
            ->assertDontSee('available here soon');
    }

    public function test_download_streams_file_with_headers(): void
    {
        $release = $this->release('standard', '1.2.0');

        $response = $this->get(route('downloads.android', 'standard'));
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive')
            ->assertHeader('Content-Length', (string) $release->size_bytes)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment; filename=NISconnect-1.2.0.apk', $response->headers->get('Content-Disposition'));
        $this->assertSame("PK\x03\x04standard1.2.0", $response->streamedContent());

        $this->release('legacy', '1.2.0');
        $legacy = $this->get(route('downloads.android', 'legacy'));
        $legacy->assertOk();
        $this->assertStringContainsString('NISconnect-1.2.0-older-phones.apk', $legacy->headers->get('Content-Disposition'));
    }

    public function test_unpublished_or_missing_builds_are_not_served(): void
    {
        $this->get(route('downloads.android', 'standard'))->assertNotFound();
        $this->get('/download/android/ios')->assertNotFound();

        $this->release('standard', '1.0.0', ['is_published' => false]);
        $this->get(route('downloads.android', 'standard'))->assertNotFound();

        // Falls back to the previous published build when the newest is unpublished.
        $this->release('standard', '0.9.0', ['created_at' => now()->subWeek()]);
        $this->assertSame("PK\x03\x04standard0.9.0", $this->get(route('downloads.android', 'standard'))->streamedContent());
    }

    public function test_latest_json(): void
    {
        $this->getJson(route('downloads.latest'))->assertOk()
            ->assertExactJson(['android' => ['standard' => null, 'legacy' => null]]);

        $release = $this->release('standard', '1.3.0');
        $this->getJson(route('downloads.latest'))->assertOk()
            ->assertExactJson(['android' => [
                'standard' => [
                    'version' => '1.3.0', 'size_bytes' => $release->size_bytes, 'sha256' => $release->sha256,
                    'url' => route('downloads.android', 'standard'),
                ],
                'legacy' => null,
            ]]);
    }

    public function test_admin_login_links_to_download_page(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee(route('downloads.index'));
    }
}
