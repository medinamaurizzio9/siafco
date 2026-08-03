<?php

namespace App\Services;

use App\Models\StoreShippingRate;
use App\Support\TextNormalizer;

class StoreShippingRateResolver
{
    public function resolve(?string $department, ?string $city = null, ?string $zone = null): ?StoreShippingRate
    {
        $department = $this->normalize($department);
        $city = $this->normalize($city);
        $zone = $this->normalize($zone);

        return $this->exactZone($department, $city, $zone)
            ?? $this->exactCity($department, $city)
            ?? $this->exactDepartment($department)
            ?? $this->national();
    }

    private function exactZone(?string $department, ?string $city, ?string $zone): ?StoreShippingRate
    {
        if (! $department || ! $city || ! $zone) {
            return null;
        }

        return $this->baseQuery()
            ->where('scope', StoreShippingRate::SCOPE_ZONE)
            ->where('department', $department)
            ->where('city', $city)
            ->where('zone', $zone)
            ->first();
    }

    private function exactCity(?string $department, ?string $city): ?StoreShippingRate
    {
        if (! $department || ! $city) {
            return null;
        }

        return $this->baseQuery()
            ->where('scope', StoreShippingRate::SCOPE_CITY)
            ->where('department', $department)
            ->where('city', $city)
            ->first();
    }

    private function exactDepartment(?string $department): ?StoreShippingRate
    {
        if (! $department) {
            return null;
        }

        return $this->baseQuery()
            ->where('scope', StoreShippingRate::SCOPE_DEPARTMENT)
            ->where('department', $department)
            ->first();
    }

    private function national(): ?StoreShippingRate
    {
        return $this->baseQuery()
            ->where('scope', StoreShippingRate::SCOPE_NATIONAL)
            ->first();
    }

    private function baseQuery()
    {
        return StoreShippingRate::query()
            ->active()
            ->orderByDesc('priority')
            ->orderBy('id');
    }

    private function normalize(?string $value): ?string
    {
        $value = TextNormalizer::uppercase((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
