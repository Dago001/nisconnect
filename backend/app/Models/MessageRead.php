<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class MessageRead extends Model
{
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = ['message_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
