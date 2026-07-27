<?php

namespace App\Support;

class TextNormalizer
{
    public static function uppercase(?string $value): ?string
    {
        return $value === null ? null : mb_strtoupper(trim($value), 'UTF-8');
    }

    public static function fields(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = self::uppercase($data[$field]);
            }
        }

        return $data;
    }
}
