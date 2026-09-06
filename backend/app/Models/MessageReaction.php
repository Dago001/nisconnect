<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class MessageReaction extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['message_id', 'user_id', 'emoji'];
}
