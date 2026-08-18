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

    public static function issuedInSelectOptions(): array
    {
        return collect(self::issuedInOptions())
            ->mapWithKeys(fn (array $option) => [$option['value'] => $option['value'].' - '.$option['label']])
            ->all();
    }

    public static function regionalOptions(): array
    {
        return collect(self::REGIONALS)
            ->mapWithKeys(fn (string $regional) => [$regional => mb_convert_case($regional, MB_CASE_TITLE, 'UTF-8')])
            ->all();
    }

    public static function maritalStatusOptions(): array
    {
        return collect(self::MARITAL_STATUSES)
            ->mapWithKeys(fn (string $status) => [$status => mb_convert_case($status, MB_CASE_TITLE, 'UTF-8')])
            ->all();
    }
}
