<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Directorate extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = ['organisation_id', 'name', 'code'];
}
