<?php

namespace App\Support;

final class PublicAffiliationCatalogs
{
    public const ISSUED_IN = ['LP', 'CB', 'SC', 'BN', 'PA', 'TR', 'CH', 'OR', 'PT'];

    public const REGIONALS = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSÍ', 'SUCRE', 'TARIJA', 'BENI', 'PANDO'];

    public const MARITAL_STATUSES = ['SOLTERO', 'CASADO', 'DIVORCIADO', 'VIUDO'];

    public static function issuedInOptions(): array
    {
        return [
            ['value' => 'LP', 'label' => 'La Paz'],
            ['value' => 'CB', 'label' => 'Cochabamba'],
            ['value' => 'SC', 'label' => 'Santa Cruz'],
            ['value' => 'BN', 'label' => 'Beni'],
            ['value' => 'PA', 'label' => 'Pando'],
            ['value' => 'TR', 'label' => 'Tarija'],
            ['value' => 'CH', 'label' => 'Chuquisaca'],
            ['value' => 'OR', 'label' => 'Oruro'],
            ['value' => 'PT', 'label' => 'Potosí'],
        ];
    }
}
