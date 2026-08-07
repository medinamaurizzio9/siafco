<x-layouts.app title="Ventas de Mini Tienda">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Ventas</h2>
            <p class="text-sm text-slate-600">Pedidos con pago confirmado. Los pedidos pendientes permanecen en el modulo operativo de Pedidos.</p>
        </div>
        <a class="btn-secondary" href="{{ route('admin.store.orders.index') }}">Ir a pedidos</a>
    </div>

    <section class="mb-5 grid gap-4 sm:grid-cols-3">
        <div class="metric-card"><p>Ventas registradas</p><strong>{{ $summary['registered_sales'] }}</strong></div>
        <div class="metric-card"><p>Monto total</p><strong>Bs {{ number_format($summary['total_amount'], 2) }}</strong></div>
        <div class="metric-card"><p>Ventas hoy</p><strong>{{ $summary['today_sales'] }}</strong></div>
    </section>

    <form class="mb-5 grid gap-3 rounded bg-white p-4 shadow md:grid-cols-5" method="get">
        <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Pedido o afiliado">
        <select class="form-input" name="delivery_method">
            <option value="">Tipo de entrega</option>
            @foreach($deliveryMethods as $method)
                <option value="{{ $method }}" @selected(($filters['delivery_method'] ?? '') === $method)>{{ $method }}</option>
            @endforeach
        </select>
        <input class="form-input" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        <input class="form-input" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        <button class="btn-secondary">Filtrar</button>
    </form>

    <div class="mobile-card-list">
        @forelse($sales as $order)
            <article class="mobile-list-card">
                <h2 class="mobile-list-card__title">{{ $order->code }}</h2>
                <p class="mobile-list-card__meta">{{ $order->affiliate?->full_name ?: 'Sin afiliado' }}</p>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-slate-500">Productos</span><strong class="block">{{ $order->items->pluck('name_snapshot')->take(2)->implode(', ') ?: 'Registrados' }}</strong></div>
                    <div><span class="text-slate-500">Total</span><strong class="block">Bs {{ number_format((float) $order->total, 2) }}</strong></div>
                    <div><span class="text-slate-500">Pago</span><strong class="block">{{ $order->status }}</strong></div>
                    <div><span class="text-slate-500">Entrega</span><strong class="block">{{ $order->delivery_method }}</strong></div>
                    <div><span class="text-slate-500">Fecha</span><strong class="block">{{ optional($order->confirmed_at)->format('d/m/Y') }}</strong></div>
                </div>
                <a class="btn-secondary mt-4 min-h-12 w-full" href="{{ route('admin.store.orders.show', $order) }}">Ver</a>
            </article>
        @empty
            <p class="mobile-list-card text-slate-600">No hay ventas registradas con los filtros actuales.</p>
        @endforelse
    </div>

    <div class="desktop-table overflow-x-auto rounded bg-white shadow">
        <table class="table min-w-full">
            <thead><tr><th>N. Pedido</th><th>Afiliado</th><th>Productos</th><th>Total</th><th>Pago</th><th>Entrega</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
            @forelse($sales as $order)
                <tr>
                    <td class="font-bold text-[#0b1f3a]">{{ $order->code }}</td>
                    <td>{{ $order->affiliate?->full_name ?: 'Sin afiliado' }}</td>
                    <td>{{ $order->items->pluck('name_snapshot')->take(2)->implode(', ') ?: 'Registrados' }}</td>
                    <td>Bs {{ number_format((float) $order->total, 2) }}</td>
                    <td><span class="rounded bg-emerald-50 px-2 py-1 text-xs font-bold text-emerald-700">{{ $order->status }}</span></td>
                    <td>{{ $order->delivery_method }}</td>
                    <td>{{ optional($order->confirmed_at)->format('d/m/Y H:i') }}</td>
                    <td class="text-right"><a class="btn-secondary" href="{{ route('admin.store.orders.show', $order) }}">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="8">No hay ventas registradas con los filtros actuales.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sales->links() }}</div>
</x-layouts.app>
