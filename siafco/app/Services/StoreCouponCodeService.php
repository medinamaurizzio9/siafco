<?php

namespace App\Services;

class StoreCouponCodeService
{
    public function normalize(string $code): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', '', $code) ?? ''));
    }

    public function hash(string $code): string
    {
        return hash('sha256', $this->normalize($code));
    }

    public function hint(string $code): string
    {
        $normalized = $this->normalize($code);

        if (mb_strlen($normalized) <= 4) {
            return str_repeat('*', mb_strlen($normalized));
        }

        return mb_substr($normalized, 0, 2)
            .str_repeat('*', max(0, mb_strlen($normalized) - 4))
            .mb_substr($normalized, -2);
    }
}
