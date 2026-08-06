<x-layouts.app title="{{ $category->exists ? 'Editar categoría' : 'Nueva categoría' }}">
    <form method="post" action="{{ $category->exists ? route('admin.store.categories.update', $category) : route('admin.store.categories.store') }}" class="section-card mx-auto grid max-w-3xl gap-4 md:grid-cols-2">
        @csrf
        @if($category->exists) @method('put') @endif
        <div>
            <label class="form-label">Nombre</label>
            <input class="form-input" name="name" value="{{ old('name', $category->name) }}" required>
        </div>
        <div>
            <label class="form-label">Slug</label>
            <input class="form-input" name="slug" value="{{ old('slug', $category->slug) }}" placeholder="se genera desde el nombre">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Descripción</label>
            <textarea class="form-input" name="description" rows="3">{{ old('description', $category->description) }}</textarea>
        </div>
        <div>
            <label class="form-label">Orden</label>
            <input class="form-input" type="number" name="order" min="0" max="9999" value="{{ old('order', $category->order ?? 0) }}" required>
        </div>
        <label class="flex items-center gap-2 pt-7">
            <input type="checkbox" name="active" value="1" @checked(old('active', $category->active ?? true))>
            Activa
        </label>
        <div class="flex gap-3 md:col-span-2">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('admin.store.categories.index') }}">Volver</a>
        </div>
    </form>
</x-layouts.app>
