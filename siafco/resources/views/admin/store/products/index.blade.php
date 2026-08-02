<x-layouts.app title="Productos de tienda">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Productos</h2>
            <p class="text-sm text-slate-600">Catálogo comercial con disponibilidad manual.</p>
        </div>
        @can('store.manage-products')
            <a class="btn-primary" href="{{ route('admin.store.products.create') }}">Nuevo producto</a>
        @endcan
    </div>

    <form class="mb-4 grid gap-2 rounded-lg border border-slate-200 bg-white p-3 md:grid-cols-[1fr_220px_220px_auto]" method="get">
        <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar nombre, SKU o slug">
        <select class="form-input" name="category">
            <option value="">Todas las categorías</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected(($filters['category'] ?? '') == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select class="form-input" name="availability_status">
            <option value="">Todos los estados</option>
            @foreach($availabilityStatuses as $status)
                <option value="{{ $status }}" @selected(($filters['availability_status'] ?? '') === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filtrar</button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Orden</th><th>Producto</th><th>Categoría</th><th>Precios</th><th>Disponibilidad</th><th></th></tr></thead>
                <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>{{ $product->order }}</td>
                        <td><strong>{{ $product->name }}</strong><br><span class="text-xs text-slate-500">{{ $product->sku }} · {{ $product->slug }}</span></td>
                        <td>{{ $product->category?->name }}</td>
                        <td>Normal Bs {{ number_format((float) $product->regular_price, 2) }}<br><strong>Afiliado Bs {{ number_format((float) $product->affiliate_price, 2) }}</strong></td>
                        <td><span class="badge">{{ $product->availability_status }}</span><br><span class="text-xs text-slate-500">{{ $product->active ? 'Activo' : 'Inactivo' }}{{ $product->featured ? ' · Destacado' : '' }}</span></td>
                        <td class="text-right">
                            @can('store.manage-products')
                                <div class="flex justify-end gap-2">
                                    <a class="btn-secondary" href="{{ route('admin.store.products.edit', $product) }}">Editar</a>
                                    <form method="post" action="{{ route('admin.store.products.destroy', $product) }}">
                                        @csrf @method('delete')
                                        <button class="btn-danger" onclick="return confirm('¿Eliminar este producto?')">Eliminar</button>
                                    </form>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay productos registrados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $products->links() }}</div>
</x-layouts.app>
