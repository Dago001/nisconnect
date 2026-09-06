<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaFile;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private media handling. Files are stored on the private disk (never a public
 * web path), validated by MIME/extension/size, and served only to authorised
 * users via an authenticated, ownership-checked download endpoint.
 */
class MediaController extends Controller
{
    private const MAX_BYTES = 52428800; // 50 MB

    private const ALLOWED = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic',
        'mp4', 'mov', 'webm',
        'mp3', 'm4a', 'aac', 'ogg', 'wav',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt',
    ];

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:51200'], // KB
            'kind' => ['required', 'in:image,video,document,audio,voice'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, self::ALLOWED, true)) {
            return response()->json(['message' => 'This file type is not permitted.'], 422);
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return response()->json(['message' => 'File is too large.'], 422);
        }

        $path = $file->store('media/'.$request->user()->id, 'private');

        $media = MediaFile::create([
            'owner_id' => $request->user()->id,
            'disk' => 'private',
            'path' => $path,
            'mime' => $file->getMimeType(),
            'extension' => $ext,
            'size_bytes' => $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'scan_status' => MediaFile::SCAN_PENDING, // a scan job flips this to clean/infected
        ]);

        return response()->json([
            'id' => $media->id,
            'kind' => $request->input('kind'),
            'download_url' => route('media.show', $media->id),
            'size_bytes' => $media->size_bytes,
        ], 201);
    }

    public function show(Request $request, MediaFile $media): StreamedResponse
    {
        abort_unless($this->canAccess($request->user()->id, $media), 403);
        abort_if($media->scan_status === MediaFile::SCAN_INFECTED, 403, 'This file failed a security scan.');
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download($media->path);
    }

    private function canAccess(string $userId, MediaFile $media): bool
    {
        if ($media->owner_id === $userId) {
            return true;
        }

        // Accessible if attached to a message in a conversation the user belongs to.
        return MessageAttachment::where('media_file_id', $media->id)
            ->whereHas('message.conversation.members', fn ($q) => $q->where('user_id', $userId)->whereNull('left_at'))
            ->exists();
    }
}
