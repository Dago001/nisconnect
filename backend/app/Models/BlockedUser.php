<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class BlockedUser extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['blocker_id', 'blocked_id'];
}
