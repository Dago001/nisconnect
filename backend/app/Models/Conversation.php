<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasUuidPrimaryKey;

    public const TYPE_DIRECT = 'direct';

    public const TYPE_GROUP = 'group';

    public const TYPE_CHANNEL = 'channel';

    protected $fillable = [
        'type', 'title', 'avatar_path', 'group_id', 'channel_id',
        'last_message_id', 'created_by',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ConversationMember::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function hasMember(string $userId): bool
    {
        return $this->members()->where('user_id', $userId)->whereNull('left_at')->exists();
    }
}
