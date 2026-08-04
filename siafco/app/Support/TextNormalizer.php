<?php

namespace App\Support;

class TextNormalizer
{
    public static function uppercase(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtoupper(self::squish($value) ?? '', 'UTF-8');
    }

    public static function lowercaseEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strtolower(trim($value), 'UTF-8');
    }

    public static function squish(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    }

    public static function normalizeFields(array $data, array $uppercaseFields): array
    {
        foreach ($uppercaseFields as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = self::uppercase($data[$field]);
            }
        }

        return $data;
    }

    public static function fields(array $data, array $fields): array
    {
        return self::normalizeFields($data, $fields);
    }
}
