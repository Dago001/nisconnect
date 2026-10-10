<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAppReleaseRequest;
use App\Models\AppRelease;
use App\Services\Admin\AppReleaseService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin portal: upload and manage the mobile app builds offered at /download. */
class AppReleaseController extends Controller
{
    public function __construct(
        private readonly AppReleaseService $releases,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        return view('admin.downloads.index', [
            'releases' => AppRelease::with('uploader')->latest()->orderByDesc('id')->paginate(20),
            'variants' => AppRelease::VARIANTS,
            'current' => collect(array_keys(AppRelease::VARIANTS))
                ->mapWithKeys(fn ($v) => [$v => $this->releases->latest($v)?->id])->filter()->flip(),
            'uploadLimit' => ini_get('upload_max_filesize'),
        ]);
    }

    public function store(StoreAppReleaseRequest $request): RedirectResponse
    {
        $release = $this->releases->upload($request->file('file'), $request->safe()->only(['variant', 'version', 'notes']), $request->user());

        $this->audit->log('app_release.uploaded', actorId: $request->user()->id, resourceType: 'app_release',
            resourceId: $release->id, metadata: $this->auditMeta($release));

        return redirect()->route('admin.downloads.index')
            ->with('status', 'NISconnect '.$release->version.' ('.AppRelease::VARIANTS[$release->variant].') is now available for download.');
    }

    public function togglePublished(Request $request, AppRelease $release): RedirectResponse
    {
        $release = $this->releases->setPublished($release, ! $release->is_published);

        $this->audit->log($release->is_published ? 'app_release.published' : 'app_release.unpublished',
            actorId: $request->user()->id, resourceType: 'app_release', resourceId: $release->id,
            metadata: $this->auditMeta($release));

        return redirect()->route('admin.downloads.index')->with('status', $release->is_published
            ? 'Version '.$release->version.' is published again.'
            : 'Version '.$release->version.' is no longer offered for download.');
    }

    public function destroy(Request $request, AppRelease $release): RedirectResponse
    {
        $meta = $this->auditMeta($release);
        $id = $release->id;
        $this->releases->delete($release);

        $this->audit->log('app_release.deleted', actorId: $request->user()->id, resourceType: 'app_release',
            resourceId: $id, metadata: $meta);

        return redirect()->route('admin.downloads.index')->with('status', 'Version '.$meta['version'].' was deleted.');
    }

    /** @return array<string, mixed> */
    private function auditMeta(AppRelease $release): array
    {
        return [
            'platform' => $release->platform, 'variant' => $release->variant, 'version' => $release->version,
            'size_bytes' => $release->size_bytes, 'sha256' => $release->sha256,
        ];
    }
}
