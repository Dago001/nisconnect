<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class MessageMention extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['message_id', 'mentioned_user_id'];
}
