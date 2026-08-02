<x-layouts.app title="Mini tienda">
    <section class="mb-6 rounded bg-[#0b1f3a] p-5 text-white shadow">
        <p class="text-sm font-bold uppercase text-[#d4af37]">SIAFCO</p>
        <h2 class="text-2xl font-black">Mini tienda para afiliados</h2>
        <p class="mt-1 text-sm text-slate-200">Productos institucionales con precios recalculados de forma segura al comprar.</p>
    </section>

    <form class="mb-5 grid gap-3 rounded bg-white p-4 shadow md:grid-cols-3" method="get">
        <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar producto">
        <select class="form-input" name="category">
            <option value="">Todas las categorías</option>
            @foreach($categories as $category)
                <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filtrar</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($products as $product)
            @php($image = $product->images->first())
            <article class="rounded bg-white shadow">
                <a href="{{ route('store.catalog.show', $product->slug) }}" class="block">
                    <div class="aspect-[4/3] overflow-hidden rounded-t bg-slate-100">
                        @if($image)
                            <img class="h-full w-full object-cover" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt ?: $product->name }}">
                        @else
                            <div class="grid h-full place-items-center text-sm text-slate-500">Sin imagen</div>
                        @endif
                    </div>
                    <div class="grid gap-2 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-black text-[#0b1f3a]">{{ $product->name }}</h3>
                            <span class="rounded bg-[#fff8df] px-2 py-1 text-xs font-bold text-[#6d5312]">{{ $product->availability_status }}</span>
                        </div>
                        <p class="line-clamp-2 text-sm text-slate-600">{{ $product->short_description ?: $product->description }}</p>
                        <p class="text-sm text-slate-500">Regular Bs {{ number_format((float) $product->regular_price, 2) }}</p>
                        <p class="text-lg font-black text-[#0b1f3a]">Afiliado Bs {{ number_format((float) $product->affiliate_price, 2) }}</p>
                        <p class="text-xs text-slate-500">{{ implode(' / ', $product->delivery_modes ?? []) }}</p>
                        <span class="btn-primary text-center">Ver producto</span>
                    </div>
                </a>
            </article>
        @empty
            <p class="rounded bg-white p-5 text-slate-600 shadow">No hay productos disponibles con estos filtros.</p>
        @endforelse
    </div>
    <div class="mt-5">{{ $products->links() }}</div>
</x-layouts.app>
