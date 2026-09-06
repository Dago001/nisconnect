<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class CallParticipant extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['call_id', 'user_id', 'state', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime'];
    }
}
