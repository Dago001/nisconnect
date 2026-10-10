<?php

namespace App\Services\Admin;

use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mobile app builds for the public download page. Files live on the default
 * (private) disk under app-releases/ with random names; the client's file
 * name is kept for reference only and never used as a path.
 */
class AppReleaseService
{
    public const DIRECTORY = 'app-releases';

    /** First bytes of every APK (it is a ZIP archive). */
    public const ZIP_SIGNATURE = "PK\x03\x04";

    /** True when the file on disk starts with the ZIP local-file signature. */
    public static function looksLikeApk(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if (! $path || ! is_readable($path)) {
            return false;
        }
        $handle = fopen($path, 'rb');
        if (! $handle) {
            return false;
        }
        $head = fread($handle, 4);
        fclose($handle);

        return $head === self::ZIP_SIGNATURE;
    }

    /**
     * @param  array{variant: string, version: string, notes?: ?string}  $data
     */
    public function upload(UploadedFile $file, array $data, User $uploader): AppRelease
    {
        $sha256 = hash_file('sha256', $file->getRealPath());
        $size = $file->getSize();
        $path = Storage::disk()->putFileAs(self::DIRECTORY, $file, Str::random(40).'.apk');
        if ($path === false) {
            throw new \RuntimeException('The app file could not be stored.');
        }

        try {
            return AppRelease::create([
                'platform' => AppRelease::PLATFORM_ANDROID,
                'variant' => $data['variant'],
                'version' => $data['version'],
                'notes' => $data['notes'] ?? null,
                'file_path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
                'size_bytes' => $size,
                'sha256' => $sha256,
                'is_published' => true,
                'uploaded_by' => $uploader->id,
            ]);
        } catch (\Throwable $e) {
            Storage::disk()->delete($path);
            throw $e;
        }
    }

    public function setPublished(AppRelease $release, bool $published): AppRelease
    {
        $release->update(['is_published' => $published]);

        return $release;
    }

    public function delete(AppRelease $release): void
    {
        $path = $release->file_path;
        DB::transaction(fn () => $release->delete());
        Storage::disk()->delete($path);
    }

    /** The release currently offered for a platform + variant, if any. */
    public function latest(string $variant, string $platform = AppRelease::PLATFORM_ANDROID): ?AppRelease
    {
        return AppRelease::published()
            ->where('platform', $platform)
            ->where('variant', $variant)
            ->latest()
            ->orderByDesc('id')
            ->first();
    }

    /** True when the stored file for a release is actually present. */
    public function fileExists(AppRelease $release): bool
    {
        return Storage::disk()->exists($release->file_path);
    }
}
