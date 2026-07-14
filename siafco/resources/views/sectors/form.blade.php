<x-layouts.app title="{{ $sector->exists ? 'Editar sector' : 'Nuevo sector' }}">
    <form method="post" action="{{ $sector->exists ? route('sectors.update', $sector) : route('sectors.store') }}" class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2">
        @csrf
        @if($sector->exists) @method('put') @endif
        <div>
            <label class="form-label">Nombre</label>
            <input class="form-input" name="name" value="{{ old('name', $sector->name) }}" required>
        </div>
        <div>
            <label class="form-label">Codigo correlativo</label>
            <input class="form-input" name="code" value="{{ old('code', $sector->code) }}" placeholder="MAG-RUR" required>
        </div>
        <div>
            <label class="form-label">Regional</label>
            <input class="form-input" name="regional" value="{{ old('regional', $sector->regional) }}">
        </div>
        <div>
            <label class="form-label">Institucion</label>
            <input class="form-input" name="institution" value="{{ old('institution', $sector->institution) }}">
        </div>
        <label class="flex items-center gap-2 md:col-span-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $sector->is_active ?? true))>
            Activo
        </label>
        <div class="flex gap-3 md:col-span-2">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('sectors.index') }}">Volver</a>
        </div>
    </form>
</x-layouts.app>
