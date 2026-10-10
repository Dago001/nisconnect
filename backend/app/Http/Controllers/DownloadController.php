<?php

namespace App\Http\Controllers;

use App\Models\AppRelease;
use App\Services\Admin\AppReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Public (unauthenticated) mobile app download page and file streams. */
class DownloadController extends Controller
{
    public function __construct(private readonly AppReleaseService $releases) {}

    public function index(): View
    {
        return view('downloads.index', [
            'standard' => $this->available('standard'),
            'legacy' => $this->available('legacy'),
        ]);
    }

    public function android(string $variant): StreamedResponse
    {
        $release = $this->available($variant);
        abort_unless($release, 404);

        return Storage::disk()->download($release->file_path, $release->downloadFilename(), [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Length' => (string) $release->size_bytes,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function latest(): JsonResponse
    {
        $android = [];
        foreach (array_keys(AppRelease::VARIANTS) as $variant) {
            $r = $this->available($variant);
            $android[$variant] = $r ? [
                'version' => $r->version,
                'size_bytes' => $r->size_bytes,
                'sha256' => $r->sha256,
                'url' => route('downloads.android', $variant),
            ] : null;
        }

        return response()->json(['android' => $android]);
    }

    private function available(string $variant): ?AppRelease
    {
        $release = $this->releases->latest($variant);

        return $release && $this->releases->fileExists($release) ? $release : null;
    }
}
