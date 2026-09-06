<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class PushToken extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['user_id', 'device_id', 'provider', 'token'];
}
