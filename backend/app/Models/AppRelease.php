<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mobile app build offered on the public download page.
 */
class AppRelease extends Model
{
    use HasUuidPrimaryKey;

    public const PLATFORM_ANDROID = 'android';

    /** variant => label */
    public const VARIANTS = [
        'standard' => 'Standard (64-bit phones)',
        'legacy' => 'Older phones (32-bit)',
    ];

    protected $fillable = [
        'platform', 'variant', 'version', 'notes', 'file_path', 'original_name',
        'size_bytes', 'sha256', 'is_published', 'uploaded_by',
    ];

    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @param  Builder<AppRelease>  $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function sizeInMb(): string
    {
        return number_format($this->size_bytes / 1048576, 1);
    }

    public function downloadFilename(): string
    {
        return 'NISconnect-'.$this->version.($this->variant === 'legacy' ? '-older-phones' : '').'.apk';
    }
}
