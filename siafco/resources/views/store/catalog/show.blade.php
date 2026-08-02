<x-layouts.app title="{{ $product->name }}">
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="grid gap-3">
            @forelse($product->images as $image)
                <img class="w-full rounded bg-white object-cover shadow" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt ?: $product->name }}">
            @empty
                <div class="grid aspect-[4/3] place-items-center rounded bg-white text-slate-500 shadow">Sin imagen</div>
            @endforelse
        </section>

        <section class="section-card">
            <p class="text-sm font-bold uppercase text-[#b8942f]">{{ $product->category->name }}</p>
            <h2 class="text-3xl font-black text-[#0b1f3a]">{{ $product->name }}</h2>
            <p class="mt-3 text-slate-700">{{ $product->description ?: $product->short_description }}</p>
            <div class="mt-4 grid gap-1">
                <p>Regular: <strong>Bs {{ number_format((float) $product->regular_price, 2) }}</strong></p>
                <p>Afiliado: <strong>Bs {{ number_format((float) $product->affiliate_price, 2) }}</strong></p>
                @if($product->promo_price && (!$product->promo_starts_at || $product->promo_starts_at->lte(now())) && (!$product->promo_ends_at || $product->promo_ends_at->gte(now())))
                    <p class="text-[#6d5312]">Promoción vigente: <strong>Bs {{ number_format((float) $product->promo_price, 2) }}</strong></p>
                @endif
                <p>Disponibilidad: <strong>{{ $product->availability_status }}</strong></p>
            </div>

            <form class="mt-5 grid gap-3" method="post" action="{{ route('store.cart.store') }}">
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
                <input class="form-input" type="number" name="quantity" min="1" max="{{ $product->max_quantity_per_order }}" value="1">
                <button class="btn-primary" @disabled($product->availability_status !== 'disponible')>Agregar al carrito</button>
            </form>
        </section>
    </div>
</x-layouts.app>
