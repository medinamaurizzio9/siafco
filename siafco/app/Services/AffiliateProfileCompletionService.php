<?php

namespace App\Services;

use App\Models\Affiliate;

class AffiliateProfileCompletionService
{
    public function summary(Affiliate $affiliate): array
    {
        $items = [
            'address' => $this->item('Dirección', 'Datos personales', 'address', filled($affiliate->address), 'Completar'),
            'birth_date' => $this->item('Fecha de nacimiento', 'Datos personales', 'birth_date', filled($affiliate->birth_date), 'Completar'),
            'marital_status' => $this->item('Estado civil', 'Datos personales', 'marital_status', filled($affiliate->marital_status), 'Completar'),
            'photo' => $this->item('Fotografía', 'Fotografía / Credencial', 'photo', filled($affiliate->photo_path), 'Agregar fotografía'),
        ];

        $total = count($items);
        $completed = collect($items)->where('complete', true)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? (int) round(($completed / $total) * 100) : 100,
            'missing' => collect($items)->reject(fn (array $item): bool => $item['complete'])->keys()->values()->all(),
            'items' => $items,
            'sections' => [
                'personal' => $this->section('Datos personales', ['address', 'birth_date', 'marital_status'], $items),
                'affiliation' => ['label' => 'Información de afiliación', 'pending' => 0, 'complete' => true],
                'photo' => $this->section('Fotografía / Credencial', ['photo'], $items),
                'security' => ['label' => 'Seguridad', 'pending' => 0, 'complete' => true],
                'payments' => ['label' => 'Mis pagos', 'pending' => null, 'complete' => null],
            ],
        ];
    }

    private function item(string $label, string $section, string $target, bool $complete, string $action): array
    {
        return compact('label', 'section', 'target', 'complete', 'action');
    }

    private function section(string $label, array $keys, array $items): array
    {
        $pending = collect($keys)
            ->filter(fn (string $key): bool => ! ($items[$key]['complete'] ?? false))
            ->count();

        return [
            'label' => $label,
            'pending' => $pending,
            'complete' => $pending === 0,
        ];
    }
}
