<?php

namespace App\Http\Controllers\Admin\Store;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Store\StoreShippingRateRequest;
use App\Models\StoreShippingRate;
use App\Services\AuditService;
use App\Services\StoreShippingRateResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ShippingRateController extends Controller
{
    public function index(Request $request, StoreShippingRateResolver $resolver)
    {
        Gate::authorize('store.view');
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'string', 'max:30'],
            'active' => ['nullable', 'in:0,1'],
            'probe_department' => ['nullable', 'string', 'max:120'],
            'probe_city' => ['nullable', 'string', 'max:120'],
            'probe_zone' => ['nullable', 'string', 'max:160'],
        ]);

        $probe = null;
        if ($request->filled('probe_department') || $request->filled('probe_city') || $request->filled('probe_zone')) {
            $probe = $resolver->resolve(
                $filters['probe_department'] ?? null,
                $filters['probe_city'] ?? null,
                $filters['probe_zone'] ?? null,
            );
        }

        return view('admin.store.shipping-rates.index', [
            'rates' => StoreShippingRate::query()
                ->when($filters['search'] ?? null, function ($query, $search): void {
                    $query->where(function ($nested) use ($search): void {
                        $nested->where('department', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%")
                            ->orWhere('zone', 'like', "%{$search}%");
                    });
                })
                ->when($filters['scope'] ?? null, fn ($query, $scope) => $query->where('scope', $scope))
                ->when(array_key_exists('active', $filters), fn ($query) => $query->where('active', (bool) $filters['active']))
                ->orderByDesc('priority')
                ->orderBy('scope')
                ->orderBy('department')
                ->orderBy('city')
                ->orderBy('zone')
                ->paginate(15)->withQueryString(),
            'filters' => $filters,
            'scopes' => $this->scopes(),
            'probe' => $probe,
        ]);
    }

    public function create()
    {
        Gate::authorize('store.manage-shipping');

        return $this->form(new StoreShippingRate([
            'scope' => StoreShippingRate::SCOPE_NATIONAL,
            'currency' => 'BOB',
            'active' => true,
            'priority' => 0,
        ]));
    }

    public function store(StoreShippingRateRequest $request)
    {
        $rate = StoreShippingRate::create($request->validated());
        AuditService::record('mini_tienda.tarifa_envio_creada', $rate, $this->auditMetadata($rate));

        return redirect()->route('admin.store.shipping-rates.index')->with('status', 'Tarifa de envio creada.');
    }

    public function edit(StoreShippingRate $shippingRate)
    {
        Gate::authorize('store.manage-shipping');

        return $this->form($shippingRate);
    }

    public function update(StoreShippingRateRequest $request, StoreShippingRate $shippingRate)
    {
        $shippingRate->update($request->validated());
        AuditService::record('mini_tienda.tarifa_envio_actualizada', $shippingRate, $this->auditMetadata($shippingRate));

        return redirect()->route('admin.store.shipping-rates.index')->with('status', 'Tarifa de envio actualizada.');
    }

    public function destroy(StoreShippingRate $shippingRate)
    {
        Gate::authorize('store.manage-shipping');
        $shippingRate->delete();
        AuditService::record('mini_tienda.tarifa_envio_desactivada', $shippingRate, $this->auditMetadata($shippingRate));

        return back()->with('status', 'Tarifa de envio eliminada de forma logica.');
    }

    private function form(StoreShippingRate $rate)
    {
        return view('admin.store.shipping-rates.form', [
            'rate' => $rate,
            'scopes' => $this->scopes(),
        ]);
    }

    private function scopes(): array
    {
        return [
            StoreShippingRate::SCOPE_NATIONAL => 'Nacional',
            StoreShippingRate::SCOPE_DEPARTMENT => 'Departamento',
            StoreShippingRate::SCOPE_CITY => 'Ciudad',
            StoreShippingRate::SCOPE_ZONE => 'Zona',
        ];
    }

    private function auditMetadata(StoreShippingRate $rate): array
    {
        return [
            'scope' => $rate->scope,
            'department' => $rate->department,
            'city' => $rate->city,
            'zone' => $rate->zone,
            'amount' => $rate->amount,
            'currency' => $rate->currency,
            'active' => $rate->active,
            'priority' => $rate->priority,
        ];
    }
}
