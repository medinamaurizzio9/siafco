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

    @if($product->exists)
        <section class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="section-card">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-xl font-black text-[#0b1f3a]">Variantes</h2>
                    <a class="btn-primary" href="{{ route('admin.store.products.variants.create', $product) }}">Nueva variante</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="table min-w-full">
                        <thead><tr><th>Orden</th><th>Tipo</th><th>Nombre</th><th>Diferencia</th><th></th></tr></thead>
                        <tbody>
                        @forelse($product->variants as $variant)
                            <tr>
                                <td>{{ $variant->order }}</td>
                                <td>{{ $variant->type }}</td>
                                <td>{{ $variant->name }}<br><span class="text-xs text-slate-500">{{ $variant->active ? 'Activa' : 'Inactiva' }}</span></td>
                                <td>Bs {{ number_format((float) $variant->price_delta, 2) }}</td>
                                <td class="text-right"><a class="btn-secondary" href="{{ route('admin.store.products.variants.edit', [$product, $variant]) }}">Editar</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Sin variantes.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="section-card">
                <h2 class="text-xl font-black text-[#0b1f3a]">Imágenes</h2>
                <form class="mt-4 grid gap-3" method="post" action="{{ route('admin.store.products.images.store', $product) }}" enctype="multipart/form-data">
                    @csrf
                    <input class="form-input" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                    <input class="form-input" name="alt" placeholder="Texto alternativo">
                    <input class="form-input" type="number" name="order" value="0" min="0" max="9999" required>
                    <label class="flex items-center gap-2"><input type="checkbox" name="is_primary" value="1"> Marcar como principal</label>
                    <button class="btn-primary">Agregar imagen</button>
                </form>
                <div class="mt-5 grid gap-3">
                    @forelse($product->images as $image)
                        <article class="rounded border border-slate-200 p-3">
                            <div class="flex gap-3">
                                <img class="h-20 w-20 rounded object-cover" src="{{ Storage::disk('public')->url($image->path) }}" alt="{{ $image->alt ?: $product->name }}">
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-[#0b1f3a]">{{ $image->is_primary ? 'Principal' : 'Imagen' }} · Orden {{ $image->order }}</p>
                                    <p class="truncate text-sm text-slate-600">{{ $image->alt ?: 'Sin texto alternativo' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <form method="post" action="{{ route('admin.store.products.images.primary', [$product, $image]) }}">@csrf<button class="btn-secondary">Principal</button></form>
                                        <form method="post" action="{{ route('admin.store.products.images.destroy', [$product, $image]) }}">@csrf @method('delete')<button class="btn-danger" onclick="return confirm('¿Eliminar imagen?')">Eliminar</button></form>
                                    </div>
                                </div>
                            </div>
                        </article>
                    @empty
                        <p class="rounded bg-slate-50 p-4 text-sm text-slate-600">Sin imágenes.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
</x-layouts.app>
