<x-layouts.app title="Tarifas de envio">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form class="grid gap-3 rounded bg-white p-4 shadow sm:grid-cols-4" method="get">
            <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Departamento, ciudad o zona">
            <select class="form-input" name="scope">
                <option value="">Todos los alcances</option>
                @foreach($scopes as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['scope'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-input" name="active">
                <option value="">Todos</option>
                <option value="1" @selected(($filters['active'] ?? '') === '1')>Activas</option>
                <option value="0" @selected(($filters['active'] ?? '') === '0')>Inactivas</option>
            </select>
            <button class="btn-secondary">Filtrar</button>
        </form>
        @can('store.manage-shipping')
            <a class="btn-primary" href="{{ route('admin.store.shipping-rates.create') }}">Nueva tarifa</a>
        @endcan
    </div>

    <section class="mb-5 rounded bg-white p-4 shadow">
        <h2 class="mb-3 text-lg font-black text-[#0b1f3a]">Probar tarifa aplicable</h2>
        <form class="grid gap-3 md:grid-cols-4" method="get">
            <input class="form-input" name="probe_department" value="{{ $filters['probe_department'] ?? '' }}" placeholder="Departamento">
            <input class="form-input" name="probe_city" value="{{ $filters['probe_city'] ?? '' }}" placeholder="Ciudad">
            <input class="form-input" name="probe_zone" value="{{ $filters['probe_zone'] ?? '' }}" placeholder="Zona">
            <button class="btn-secondary">Probar</button>
        </form>
        @if(($filters['probe_department'] ?? null) || ($filters['probe_city'] ?? null) || ($filters['probe_zone'] ?? null))
            <p class="mt-3 rounded bg-slate-50 p-3 text-sm text-slate-700">
                @if($probe)
                    Tarifa encontrada: <strong>{{ $scopes[$probe->scope] ?? $probe->scope }}</strong> - Bs {{ number_format((float) $probe->amount, 2) }}.
                @else
                    No existe una tarifa activa aplicable.
                @endif
            </p>
        @endif
    </section>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="table min-w-full">
            <thead>
                <tr>
                    <th>Prioridad</th>
                    <th>Alcance</th>
                    <th>Ubicacion</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse($rates as $rate)
                <tr>
                    <td>{{ $rate->priority }}</td>
                    <td>{{ $scopes[$rate->scope] ?? $rate->scope }}</td>
                    <td>{{ collect([$rate->department, $rate->city, $rate->zone])->filter()->implode(' / ') ?: 'Todo el pais' }}</td>
                    <td>Bs {{ number_format((float) $rate->amount, 2) }}</td>
                    <td>{{ $rate->active ? 'Activa' : 'Inactiva' }}</td>
                    <td class="text-right">
                        @can('store.manage-shipping')
                            <a class="btn-secondary" href="{{ route('admin.store.shipping-rates.edit', $rate) }}">Editar</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">No hay tarifas registradas.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rates->links() }}</div>
</x-layouts.app>
