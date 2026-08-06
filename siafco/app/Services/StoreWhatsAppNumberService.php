<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class StoreWhatsAppNumberService
{
    public function normalize(?string $number): ?string
    {
        $clean = preg_replace('/[\s().-]+/u', '', trim((string) $number));
        if ($clean === '') {
            return null;
        }

        if (preg_match('/^\+?591\d{8}$/', $clean)) {
            return '591'.substr($clean, -8);
        }

        if (preg_match('/^\d{8}$/', $clean)) {
            return '591'.$clean;
        }

        if (preg_match('/^\+?[1-9]\d{7,14}$/', $clean)) {
            return ltrim($clean, '+');
        }

        throw ValidationException::withMessages([
            'whatsapp_number' => 'Ingresa un número de WhatsApp válido.',
        ]);
    }

    public function hash(?string $number): ?string
    {
        $normalized = $this->normalize($number);

        return $normalized ? hash('sha256', $normalized) : null;
    }

    public function hint(?string $number): ?string
    {
        $normalized = $this->normalize($number);
        if (! $normalized) {
            return null;
        }

        return substr($normalized, 0, 3)
            .str_repeat('*', max(0, strlen($normalized) - 6))
            .substr($normalized, -3);
    }

    public function waMeUrl(string $normalizedNumber, string $message = ''): string
    {
        $url = 'https://wa.me/'.$this->normalize($normalizedNumber);

        return $message !== '' ? $url.'?text='.rawurlencode($message) : $url;
    }
}
