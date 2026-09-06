<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    use HasUuidPrimaryKey;

    public const TYPE_VOICE = 'voice';
    public const TYPE_VIDEO = 'video';
    public const MODE_DIRECT = 'direct';
    public const MODE_GROUP = 'group';

    protected $fillable = [
        'conversation_id', 'type', 'mode', 'initiator_id', 'room_name',
        'status', 'started_at', 'ended_at',
    ];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }
}
