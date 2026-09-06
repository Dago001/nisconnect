<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Organisation extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['name', 'code'];
}
