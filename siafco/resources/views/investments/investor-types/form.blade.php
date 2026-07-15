<x-layouts.app title="{{ $type->exists ? 'Editar tipo' : 'Nuevo tipo' }}">
    <form class="section-card grid gap-4 md:grid-cols-2" method="post" action="{{ $type->exists ? route('investments.investor-types.update', $type) : route('investments.investor-types.store') }}">
        @csrf
        @if($type->exists) @method('put') @endif
        <div>
            <label class="form-label">Nombre</label>
            <input class="form-input" name="name" value="{{ old('name', $type->name) }}" required>
        </div>
        <div>
            <label class="form-label">Cantidad de acciones</label>
            <input class="form-input" type="number" name="shares_quantity" min="1" value="{{ old('shares_quantity', $type->shares_quantity) }}" required>
        </div>
        <div>
            <label class="form-label">Orden</label>
            <input class="form-input" type="number" name="order" min="0" value="{{ old('order', $type->order ?? 0) }}">
        </div>
        <label class="flex items-center gap-2 pt-7 font-bold"><input type="checkbox" name="active" value="1" @checked(old('active', $type->active ?? true))> Activo</label>
        <div class="md:col-span-2">
            <label class="form-label">Descripcion</label>
            <textarea class="form-input" name="description" rows="3">{{ old('description', $type->description) }}</textarea>
        </div>
        <div class="md:col-span-2 flex gap-3">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('investments.investor-types.index') }}">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
