<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Formation extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['command_id', 'name', 'code', 'type'];
}
