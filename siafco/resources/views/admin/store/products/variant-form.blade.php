<x-layouts.app title="{{ $variant->exists ? 'Editar variante' : 'Nueva variante' }}">
    <form method="post" action="{{ $variant->exists ? route('admin.store.products.variants.update', [$product, $variant]) : route('admin.store.products.variants.store', $product) }}" class="section-card mx-auto grid max-w-3xl gap-4 md:grid-cols-2">
        @csrf
        @if($variant->exists) @method('put') @endif
        <div class="md:col-span-2">
            <p class="text-sm font-bold uppercase text-[#b8942f]">{{ $product->sku }}</p>
            <h2 class="text-xl font-black text-[#0b1f3a]">{{ $product->name }}</h2>
        </div>
        <div><label class="form-label">Tipo</label><input class="form-input" name="type" value="{{ old('type', $variant->type) }}" placeholder="TALLA, COLOR, MATERIAL" required></div>
        <div><label class="form-label">Nombre</label><input class="form-input" name="name" value="{{ old('name', $variant->name) }}" required></div>
        <div><label class="form-label">Sufijo SKU</label><input class="form-input" name="sku_suffix" value="{{ old('sku_suffix', $variant->sku_suffix) }}"></div>
        <div><label class="form-label">Diferencia de precio</label><input class="form-input" type="number" step="0.01" name="price_delta" value="{{ old('price_delta', $variant->price_delta ?? 0) }}" required></div>
        <div><label class="form-label">Orden</label><input class="form-input" type="number" name="order" min="0" max="9999" value="{{ old('order', $variant->order ?? 0) }}" required></div>
        <label class="flex items-center gap-2 pt-7"><input type="checkbox" name="active" value="1" @checked(old('active', $variant->active ?? true))> Activa</label>
        <div class="flex gap-3 md:col-span-2">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('admin.store.products.edit', $product) }}">Volver</a>
        </div>
    </form>
    @if($variant->exists)
        <form class="mx-auto mt-4 max-w-3xl" method="post" action="{{ route('admin.store.products.variants.destroy', [$product, $variant]) }}">
            @csrf @method('delete')
            <button class="btn-danger" onclick="return confirm('¿Eliminar esta variante?')">Eliminar variante</button>
        </form>
    @endif
</x-layouts.app>
