<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PersonnelRecord extends Model
{
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'service_number', 'surname', 'first_name', 'other_name', 'rank',
        'directorate', 'department', 'zone', 'command', 'formation', 'unit',
        'posting', 'official_email', 'status', 'photo_path', 'source', 'source_synced_at',
    ];

    protected function casts(): array
    {
        return ['source_synced_at' => 'datetime'];
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
