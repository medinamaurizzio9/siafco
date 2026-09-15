<x-layouts.app title="{{ $product->name }}">
    @php($isAvailable = $product->availability_status === \App\Support\StoreAvailabilityStatus::AVAILABLE)

    <div class="store-pwa-shell">
        <a class="store-pwa-back" href="{{ route('store.catalog.index') }}">
            <x-ui.icon name="arrow-left" class="h-5 w-5" />
            <span>Tienda</span>
        </a>

        <div class="store-pwa-detail">
            <section class="store-pwa-detail__gallery">
                @forelse($product->images as $image)
                    <img loading="lazy" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt ?: $product->name }}">
                @empty
                    <div>Sin imagen</div>
                @endforelse
            </section>

            <section class="store-pwa-detail__info">
                <div class="store-pwa-detail__meta">
                    <span>{{ $product->category->name }}</span>
                    <strong class="{{ $isAvailable ? 'is-available' : 'is-muted' }}">{{ $product->availability_status }}</strong>
                </div>
                <h2>{{ $product->name }}</h2>
                <p>{{ $product->description ?: $product->short_description }}</p>
                <div class="store-pwa-price">
                    <small>Precio afiliado</small>
                    <strong>Bs {{ number_format((float) $product->affiliate_price, 2) }}</strong>
                    @if($product->promo_price && (!$product->promo_starts_at || $product->promo_starts_at->lte(now())) && (!$product->promo_ends_at || $product->promo_ends_at->gte(now())))
                        <span>Promoción Bs {{ number_format((float) $product->promo_price, 2) }}</span>
                    @endif
                </div>

                <form class="store-pwa-buybox" method="post" action="{{ route('store.cart.store') }}">
                    @csrf
                    <input type="hidden" name="product_public_code" value="{{ $product->public_code }}">
                    @if($product->variants->isNotEmpty())
                        <select class="form-input" name="variant_public_code">
                            <option value="">Sin variante</option>
                            @foreach($product->variants as $variant)
                                <option value="{{ $variant->public_code }}">{{ $variant->type }} {{ $variant->name }} ({{ (float) $variant->price_delta >= 0 ? '+' : '' }}Bs {{ number_format((float) $variant->price_delta, 2) }})</option>
                            @endforeach
                        </select>
                    @endif
                    <label>
                        <span>Cantidad</span>
                        <input class="form-input" type="number" name="quantity" min="1" max="{{ $product->max_quantity_per_order }}" value="1">
                    </label>
                    <button class="btn-primary" @disabled(! $isAvailable)>Agregar al carrito</button>
                </form>
            </section>
        </div>
    </div>
</x-layouts.app>
