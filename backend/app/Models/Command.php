<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Command extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['zone_id', 'name', 'code'];
}
