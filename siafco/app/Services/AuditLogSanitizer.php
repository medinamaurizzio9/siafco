<?php

namespace App\Services;

class AuditLogSanitizer
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'token', 'access_token',
        'remember_token', 'api_key', 'secret', 'hash', 'verification_token', 'public_token',
        'qr', 'qr_image', 'base64', 'voucher_path', 'receipt_path', 'photo_path', 'path',
    ];

    public function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $sanitized = [];
        foreach ($value as $key => $item) {
            $keyString = (string) $key;
            if ($this->isSensitive($keyString)) {
                $sanitized[$keyString] = '[REDACTADO]';
                continue;
            }

            $sanitized[$keyString] = is_array($item) ? $this->sanitize($item) : $item;
        }

        return $sanitized;
    }

    public function summary(?array $metadata): string
    {
        $metadata = $this->sanitize($metadata ?? []);
        $fields = data_get($metadata, 'fields');
        if (is_array($fields) && $fields !== []) {
            $fields = $this->normalizeFieldNames($fields);

            if ($fields === []) {
                return 'Sin detalles';
            }

            return 'Campos: '.implode(', ', array_slice($fields, 0, 5));
        }

        $result = data_get($metadata, 'result');
        if ($result) {
            return 'Resultado: '.$result;
        }

        return collect($metadata)->keys()->take(4)->implode(', ') ?: 'Sin resumen';
    }

    private function normalizeFieldNames(array $fields): array
    {
        $normalized = [];

        foreach ($fields as $key => $item) {
            if (is_string($item) || is_numeric($item)) {
                $normalized[] = (string) $item;
                continue;
            }

            if (! is_array($item)) {
                continue;
            }

            $field = data_get($item, 'field');
            if (is_string($field) || is_numeric($field)) {
                $normalized[] = (string) $field;
                continue;
            }

            if ($this->isAssociative($item) && is_string($key)) {
                $normalized[] = $key;
                continue;
            }

            $normalized = [...$normalized, ...$this->normalizeFieldNames($item)];
        }

        return collect($normalized)
            ->map(fn (string $field) => trim($field))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isAssociative(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function isSensitive(string $key): bool
    {
        $key = str($key)->lower()->toString();

        return collect(self::SENSITIVE_KEYS)->contains(fn (string $needle) => str_contains($key, $needle));
    }
}
