<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasPublicUuid
{
    protected static function bootHasPublicUuid(): void
    {
        static::creating(function ($model): void {
            $column = defined(static::class.'::PUBLIC_UUID_COLUMN')
                ? constant(static::class.'::PUBLIC_UUID_COLUMN')
                : 'public_id';

            if (! $model->{$column}) {
                $model->{$column} = (string) Str::uuid();
            }
        });
    }
}
