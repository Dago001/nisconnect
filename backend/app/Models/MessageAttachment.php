<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['message_id', 'media_file_id', 'kind'];
}
