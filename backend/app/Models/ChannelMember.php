<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class ChannelMember extends Model
{
    use HasUuidPrimaryKey;

    public const ROLE_PUBLISHER = 'publisher';

    public const ROLE_SUBSCRIBER = 'subscriber';

    protected $fillable = ['channel_id', 'user_id', 'role'];
}
