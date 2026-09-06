<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageAttachment extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['message_id', 'media_file_id', 'kind'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }
}
