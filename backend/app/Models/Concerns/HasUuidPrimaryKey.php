<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Assigns a UUID primary key on create and configures the key type.
 */
trait HasUuidPrimaryKey
{
    public static function bootHasUuidPrimaryKey(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();
            if (empty($model->{$key})) {
                $model->{$key} = (string) Str::uuid();
            }
        });
    }

    public function initializeHasUuidPrimaryKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
