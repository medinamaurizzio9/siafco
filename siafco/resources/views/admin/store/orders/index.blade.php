<x-layouts.app title="Pedidos de tienda">
    <form class="mb-5 grid gap-3 rounded bg-white p-4 shadow md:grid-cols-5" method="get">
        <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Código o afiliado">
        <select class="form-input" name="status">
            <option value="">Todos los estados</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <select class="form-input" name="delivery_method">
            <option value="">Entrega</option>
            @foreach($deliveryMethods as $method)
                <option value="{{ $method }}" @selected(($filters['delivery_method'] ?? '') === $method)>{{ $method }}</option>
            @endforeach
        </select>
        <input class="form-input" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        <button class="btn-secondary">Filtrar</button>
    </form>

    <div class="mobile-card-list">
        @forelse($orders as $order)
            <article class="mobile-list-card">
                <h2 class="mobile-list-card__title">{{ $order->code }}</h2>
                <p class="mobile-list-card__meta">{{ $order->affiliate?->full_name }}</p>
                <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-slate-500">Estado</span><strong class="block">{{ $order->status }}</strong></div>
                    <div><span class="text-slate-500">Total</span><strong class="block">Bs {{ number_format((float) $order->total, 2) }}</strong></div>
                    <div><span class="text-slate-500">Entrega</span><strong class="block">{{ $order->delivery_method }}</strong></div>
                    <div><span class="text-slate-500">Fecha</span><strong class="block">{{ $order->created_at->format('d/m/Y') }}</strong></div>
                </div>
                <a class="btn-secondary mt-4 min-h-12 w-full" href="{{ route('admin.store.orders.show', $order) }}">Ver</a>
            </article>
        @empty
            <p class="mobile-list-card text-slate-600">No hay pedidos registrados.</p>
        @endforelse
    </div>

    <div class="desktop-table overflow-x-auto rounded bg-white shadow">
        <table class="table min-w-full">
            <thead><tr><th>Código</th><th>Afiliado</th><th>Estado</th><th>Entrega</th><th>Total</th><th>Fecha</th><th></th></tr></thead>
            <tbody>
            @forelse($orders as $order)
                <tr>
                    <td class="font-bold text-[#0b1f3a]">{{ $order->code }}</td>
                    <td>{{ $order->affiliate?->full_name }}</td>
                    <td><span class="rounded bg-slate-100 px-2 py-1 text-xs font-bold">{{ $order->status }}</span></td>
                    <td>{{ $order->delivery_method }}</td>
                    <td>Bs {{ number_format((float) $order->total, 2) }}</td>
                    <td>{{ $order->created_at->format('d/m/Y H:i') }}</td>
                    <td class="text-right"><a class="btn-secondary" href="{{ route('admin.store.orders.show', $order) }}">Ver</a></td>
                </tr>
            @empty
                <tr><td colspan="7">No hay pedidos registrados.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-layouts.app>
