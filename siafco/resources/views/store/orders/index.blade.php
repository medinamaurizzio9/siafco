<x-layouts.app title="Mis pedidos">
    <div class="store-pwa-shell">
        <nav class="store-pwa-tabs" aria-label="Accesos de tienda">
            <a href="{{ route('store.catalog.index') }}">Tienda</a>
            <a href="{{ route('store.cart.show') }}">Carrito</a>
            <a class="is-active" href="{{ route('store.orders.index') }}">Pedidos</a>
        </nav>

        <form class="store-pwa-filter" method="get">
            <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Código">
            <select class="form-input" name="status"><option value="">Todos</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select>
            <input class="form-input" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            <button class="btn-secondary">Filtrar</button>
        </form>
        <div class="store-pwa-list">
            @forelse($orders as $order)
                <article class="store-pwa-order">
                    <div>
                        <span>{{ $order->status }}</span>
                        <h3>{{ $order->code }}</h3>
                        <p>{{ $order->created_at->format('d/m/Y H:i') }} · {{ $order->delivery_method }}</p>
                    </div>
                    <strong>Bs {{ number_format((float) $order->total, 2) }}</strong>
                    <a class="btn-secondary" href="{{ route('store.orders.show', $order) }}">Ver pedido</a>
                </article>
            @empty
                <p class="store-pwa-empty">No tienes pedidos registrados.</p>
            @endforelse
        </div>
        <div class="store-pwa-pagination">{{ $orders->links() }}</div>
    </div>
</x-layouts.app>
