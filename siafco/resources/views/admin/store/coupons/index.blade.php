<x-layouts.app title="Cupones de tienda">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form class="grid gap-3 rounded bg-white p-4 shadow sm:grid-cols-4" method="get">
            <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Hint del código">
            <select class="form-input" name="type">
                <option value="">Todos los tipos</option>
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select class="form-input" name="active">
                <option value="">Todos</option>
                <option value="1" @selected(($filters['active'] ?? '') === '1')>Activos</option>
                <option value="0" @selected(($filters['active'] ?? '') === '0')>Inactivos</option>
            </select>
            <button class="btn-secondary">Filtrar</button>
        </form>
        @can('store.manage-coupons')
            <a class="btn-primary" href="{{ route('admin.store.coupons.create') }}">Nuevo cupón</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded bg-white shadow">
        <table class="table min-w-full">
            <thead><tr><th>Código</th><th>Tipo</th><th>Valor</th><th>Vigencia</th><th>Límites</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            @forelse($coupons as $coupon)
                <tr>
                    <td>{{ $coupon->code_hint }}</td>
                    <td>{{ $types[$coupon->type] ?? $coupon->type }}</td>
                    <td>{{ $coupon->type === 'percentage' ? number_format((float) $coupon->value, 2).'%' : 'Bs '.number_format((float) $coupon->value, 2) }}</td>
                    <td class="text-sm">{{ $coupon->starts_at?->format('d/m/Y H:i') ?: 'Sin inicio' }}<br>{{ $coupon->ends_at?->format('d/m/Y H:i') ?: 'Sin vencimiento' }}</td>
                    <td class="text-sm">Global: {{ $coupon->global_limit ?: 'Sin límite' }}<br>Afiliado: {{ $coupon->per_affiliate_limit ?: 'Sin límite' }}</td>
                    <td>{{ $coupon->active ? 'Activo' : 'Inactivo' }}</td>
                    <td class="text-right">
                        @can('store.manage-coupons')
                            <a class="btn-secondary" href="{{ route('admin.store.coupons.edit', $coupon) }}">Editar</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">No hay cupones registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $coupons->links() }}</div>
</x-layouts.app>
