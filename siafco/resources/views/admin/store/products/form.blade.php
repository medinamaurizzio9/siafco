<x-layouts.app title="{{ $product->exists ? 'Editar producto' : 'Nuevo producto' }}">
    <form method="post" action="{{ $product->exists ? route('admin.store.products.update', $product) : route('admin.store.products.store') }}" class="section-card grid gap-4 md:grid-cols-2">
        @csrf
        @if($product->exists) @method('put') @endif
        <div><label class="form-label">Categoría</label><select class="form-input" name="store_category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('store_category_id', $product->store_category_id)==$category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div><label class="form-label">SKU</label><input class="form-input" name="sku" value="{{ old('sku', $product->sku) }}" required></div>
        <div><label class="form-label">Nombre</label><input class="form-input" name="name" value="{{ old('name', $product->name) }}" required></div>
        <div><label class="form-label">Slug</label><input class="form-input" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="se genera desde el nombre"></div>
        <div><label class="form-label">Precio normal</label><input class="form-input" type="number" step="0.01" min="0" name="regular_price" value="{{ old('regular_price', $product->regular_price) }}" required></div>
        <div><label class="form-label">Precio afiliado</label><input class="form-input" type="number" step="0.01" min="0" name="affiliate_price" value="{{ old('affiliate_price', $product->affiliate_price) }}" required></div>
        <div><label class="form-label">Precio promocional</label><input class="form-input" type="number" step="0.01" min="0" name="promo_price" value="{{ old('promo_price', $product->promo_price) }}"></div>
        <div class="grid gap-2 sm:grid-cols-2"><div><label class="form-label">Inicio promoción</label><input class="form-input" type="datetime-local" name="promo_starts_at" value="{{ old('promo_starts_at', $product->promo_starts_at?->format('Y-m-d\TH:i')) }}"></div><div><label class="form-label">Fin promoción</label><input class="form-input" type="datetime-local" name="promo_ends_at" value="{{ old('promo_ends_at', $product->promo_ends_at?->format('Y-m-d\TH:i')) }}"></div></div>
        <div><label class="form-label">Disponibilidad</label><select class="form-input" name="availability_status" required>@foreach($availabilityStatuses as $status)<option value="{{ $status }}" @selected(old('availability_status', $product->availability_status)===$status)>{{ $status }}</option>@endforeach</select></div>
        <div><label class="form-label">Cantidad máxima por pedido</label><input class="form-input" type="number" name="max_quantity_per_order" min="1" max="100" value="{{ old('max_quantity_per_order', $product->max_quantity_per_order ?? 10) }}" required></div>
        <div class="md:col-span-2"><label class="form-label">Descripción corta</label><textarea class="form-input" name="short_description" rows="2">{{ old('short_description', $product->short_description) }}</textarea></div>
        <div class="md:col-span-2"><label class="form-label">Descripción completa</label><textarea class="form-input" name="description" rows="5">{{ old('description', $product->description) }}</textarea></div>
        <fieldset class="rounded border border-slate-200 p-3">
            <legend class="px-1 text-sm font-bold text-slate-700">Modalidades de entrega</legend>
            @foreach($deliveryMethods as $method)
                <label class="mr-4 inline-flex items-center gap-2"><input type="checkbox" name="delivery_modes[]" value="{{ $method }}" @checked(in_array($method, old('delivery_modes', $product->delivery_modes ?? []), true))> {{ $method }}</label>
            @endforeach
        </fieldset>
        <div class="grid gap-2">
            <label class="flex items-center gap-2"><input type="checkbox" name="featured" value="1" @checked(old('featured', $product->featured ?? false))> Destacado</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked(old('active', $product->active ?? true))> Activo</label>
        </div>
        <div><label class="form-label">Orden</label><input class="form-input" type="number" name="order" min="0" max="9999" value="{{ old('order', $product->order ?? 0) }}" required></div>
        <div class="flex gap-3 md:col-span-2">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('admin.store.products.index') }}">Volver</a>
        </div>
    </form>
</x-layouts.app>
