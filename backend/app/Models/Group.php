<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Group extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    public const TYPE_STANDARD = 'standard';

    public const TYPE_ORGANISATIONAL = 'organisational';

    protected $fillable = [
        'conversation_id', 'name', 'description', 'avatar_path', 'type',
        'org_scope_type', 'org_scope_id', 'created_by', 'permissions',
    ];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }
}
