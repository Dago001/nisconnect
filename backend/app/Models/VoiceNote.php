<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceNote extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['message_id', 'media_file_id', 'duration_ms', 'waveform'];

    protected function casts(): array
    {
        return ['waveform' => 'array'];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
