<?php

namespace Tests\Unit;

use App\Support\TextNormalizer;
use PHPUnit\Framework\TestCase;

class TextNormalizerTest extends TestCase
{
    public function test_uppercase_preserves_spanish_unicode_and_squishes_spaces(): void
    {
        $this->assertSame('MAURIZZIO MEDINA', TextNormalizer::uppercase('  Maurizzio   Medina  '));
        $this->assertSame('LA PAZ', TextNormalizer::uppercase('la paz'));
        $this->assertSame('ÑUÑOA', TextNormalizer::uppercase('Ñuñoa'));
        $this->assertSame('Á É Í Ó Ú', TextNormalizer::uppercase('á   é í ó ú'));
    }

    public function test_email_is_lowercased_and_sensitive_or_technical_fields_are_not_touched(): void
    {
        $payload = [
            'email' => TextNormalizer::lowercaseEmail(' USER@Example.COM '),
            'password' => 'MiClaveSegura123',
            'url' => 'https://example.test/Ruta?Q=Si',
            'public_code' => '550e8400-e29b-41d4-a716-446655440000',
            'slug' => 'mi-slug-tecnico',
            'access_token' => 'TokenConMayusculas',
            'status' => 'pendiente_pago',
            'address' => ' calle  uno ',
        ];

        $normalized = TextNormalizer::normalizeFields($payload, ['address']);

        $this->assertSame('user@example.com', $normalized['email']);
        $this->assertSame('MiClaveSegura123', $normalized['password']);
        $this->assertSame('https://example.test/Ruta?Q=Si', $normalized['url']);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $normalized['public_code']);
        $this->assertSame('mi-slug-tecnico', $normalized['slug']);
        $this->assertSame('TokenConMayusculas', $normalized['access_token']);
        $this->assertSame('pendiente_pago', $normalized['status']);
        $this->assertSame('CALLE UNO', $normalized['address']);
    }
}
