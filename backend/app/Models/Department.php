<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['directorate_id', 'name', 'code'];
}
