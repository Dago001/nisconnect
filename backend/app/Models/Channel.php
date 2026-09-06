<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use HasUuidPrimaryKey;
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'avatar_path', 'org_scope_type', 'org_scope_id', 'created_by',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ChannelMember::class);
    }
}
