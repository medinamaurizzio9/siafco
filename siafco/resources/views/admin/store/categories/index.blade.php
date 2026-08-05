<x-layouts.app title="Categorías de tienda">
    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Categorías</h2>
            <p class="text-sm text-slate-600">Organiza productos y beneficios comerciales.</p>
        </div>
        @can('store.manage-products')
            <a class="btn-primary" href="{{ route('admin.store.categories.create') }}">Nueva categoría</a>
        @endcan
    </div>

    <form class="mb-4 flex gap-2" method="get">
        <input class="form-input max-w-sm" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar por nombre o slug">
        <button class="btn-secondary">Buscar</button>
    </form>

    <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Orden</th><th>Categoría</th><th>Slug</th><th>Productos</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>{{ $category->order }}</td>
                        <td><strong>{{ $category->name }}</strong><br><span class="text-xs text-slate-500">{{ $category->description }}</span></td>
                        <td>{{ $category->slug }}</td>
                        <td>{{ $category->products_count }}</td>
                        <td><span class="badge">{{ $category->active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-right">
                            @can('store.manage-products')
                                <div class="flex justify-end gap-2">
                                    <a class="btn-secondary" href="{{ route('admin.store.categories.edit', $category) }}">Editar</a>
                                    <form method="post" action="{{ route('admin.store.categories.destroy', $category) }}">
                                        @csrf @method('delete')
                                        <button class="btn-danger" onclick="return confirm('¿Eliminar esta categoría?')">Eliminar</button>
                                    </form>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6">No hay categorías registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $categories->links() }}</div>
</x-layouts.app>
