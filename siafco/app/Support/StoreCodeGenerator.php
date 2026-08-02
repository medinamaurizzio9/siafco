<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StoreCodeGenerator
{
    public static function orderCode(): string
    {
        return 'PED-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
    }

    public static function redemptionCode(): string
    {
        return 'CAN-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
    }

    public static function assignUnique(Model $model, string $column, callable $factory): void
    {
        if ($model->{$column}) {
            return;
        }

        $modelClass = $model::class;
        do {
            $code = $factory();
        } while ($modelClass::query()->where($column, $code)->exists());

        $model->{$column} = $code;
    }
}
