<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['formation_id', 'name', 'code'];
}
