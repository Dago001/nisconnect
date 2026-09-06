<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'reporter_id', 'target_type', 'target_id', 'reason', 'details',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }
}
