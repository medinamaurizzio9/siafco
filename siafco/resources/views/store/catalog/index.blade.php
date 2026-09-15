<x-layouts.app title="Tienda">
    @php
        $selectedCategory = $filters['category'] ?? '';
        $cartCount = collect(session('store_cart.lines', []))->sum('quantity');
    @endphp

    <div class="store-pwa-shell">
        <section class="store-pwa-hero">
            <div>
                <p>SIAFCO</p>
                <h2>Mini tienda</h2>
                <span>Productos y beneficios para afiliados activos.</span>
            </div>
            <a href="{{ route('store.cart.show') }}" aria-label="Abrir carrito">
                <x-ui.icon name="package" class="h-5 w-5" />
                @if($cartCount > 0)
                    <strong>{{ $cartCount }}</strong>
                @endif
            </a>
        </section>

        <form class="store-pwa-search" method="get">
            <label class="store-pwa-search__box">
                <x-ui.icon name="search" class="h-5 w-5" />
                <input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar producto">
            </label>
            @if($selectedCategory)
                <input type="hidden" name="category" value="{{ $selectedCategory }}">
            @endif
            <button class="btn-secondary" aria-label="Buscar productos">
                <x-ui.icon name="search" class="h-5 w-5" />
            </button>
        </form>

        <nav class="store-pwa-tabs" aria-label="Categorías de tienda">
            <a class="{{ $selectedCategory === '' ? 'is-active' : '' }}" href="{{ route('store.catalog.index', array_filter(['search' => $filters['search'] ?? null])) }}">Todos</a>
            @foreach($categories as $category)
                <a class="{{ $selectedCategory === $category->slug ? 'is-active' : '' }}" href="{{ route('store.catalog.index', array_filter(['search' => $filters['search'] ?? null, 'category' => $category->slug])) }}">{{ $category->name }}</a>
            @endforeach
            <a href="{{ route('affiliate.benefits') }}">Beneficios</a>
            <a href="{{ route('store.orders.index') }}">Mis pedidos</a>
        </nav>

        <div class="store-pwa-grid">
            @forelse($products as $product)
                @php
                    $image = $product->images->first();
                    $isAvailable = $product->availability_status === \App\Support\StoreAvailabilityStatus::AVAILABLE;
                @endphp
                <article class="store-pwa-product">
                    <a href="{{ route('store.catalog.show', $product->slug) }}" class="store-pwa-product__media">
                        @if($image)
                            <img loading="lazy" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt ?: $product->name }}">
                        @else
                            <span>Sin imagen</span>
                        @endif
                        <small class="{{ $isAvailable ? 'is-available' : 'is-muted' }}">{{ $product->availability_status }}</small>
                    </a>
                    <div class="store-pwa-product__body">
                        <a href="{{ route('store.catalog.show', $product->slug) }}">
                            <h3>{{ $product->name }}</h3>
                        </a>
                        <p>{{ $product->short_description ?: $product->description }}</p>
                        <strong>Bs {{ number_format((float) $product->affiliate_price, 2) }}</strong>
                        <form method="post" action="{{ route('store.cart.store') }}">
                            @csrf
                            <input type="hidden" name="product_public_code" value="{{ $product->public_code }}">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn-primary" @disabled(! $isAvailable)>Agregar</button>
                        </form>
                    </div>
                </article>
            @empty
                <p class="store-pwa-empty">No hay productos disponibles con estos filtros.</p>
            @endforelse
        </div>
        <div class="store-pwa-pagination">{{ $products->links() }}</div>
    </div>
</x-layouts.app>
