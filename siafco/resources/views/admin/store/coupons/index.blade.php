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

    <div class="mobile-card-list">
        @forelse($coupons as $coupon)
            <article class="mobile-list-card">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="mobile-list-card__title truncate">{{ $coupon->code_hint }}</h2>
                        <p class="mobile-list-card__meta">{{ $types[$coupon->type] ?? $coupon->type }}</p>
                    </div>
                    <span class="badge">{{ $coupon->active ? 'Activo' : 'Inactivo' }}</span>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-slate-500">Valor</span><strong class="block">{{ $coupon->type === 'percentage' ? number_format((float) $coupon->value, 2).'%' : 'Bs '.number_format((float) $coupon->value, 2) }}</strong></div>
                    <div><span class="text-slate-500">Limite</span><strong class="block">{{ $coupon->global_limit ?: 'Sin limite' }}</strong></div>
                    <div class="col-span-2"><span class="text-slate-500">Vigencia</span><strong class="block">{{ $coupon->starts_at?->format('d/m/Y H:i') ?: 'Sin inicio' }} - {{ $coupon->ends_at?->format('d/m/Y H:i') ?: 'Sin vencimiento' }}</strong></div>
                </div>
                @can('store.manage-coupons')
                    <a class="btn-secondary mt-4 min-h-12 w-full" href="{{ route('admin.store.coupons.edit', $coupon) }}">Editar</a>
                @endcan
            </article>
        @empty
            <p class="mobile-list-card text-slate-600">No hay cupones registrados.</p>
        @endforelse
    </div>

    <div class="desktop-table overflow-x-auto rounded bg-white shadow">
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
