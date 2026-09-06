<?php

namespace App\Models;

use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasUuidPrimaryKey;

    protected $fillable = [
        'user_id', 'phone', 'purpose', 'code_hash', 'attempts', 'max_attempts',
        'expires_at', 'consumed_at', 'last_sent_at', 'resend_count',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
