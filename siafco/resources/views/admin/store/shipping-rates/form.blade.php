<x-layouts.app title="{{ $rate->exists ? 'Editar tarifa de envio' : 'Nueva tarifa de envio' }}">
    <form method="post" action="{{ $rate->exists ? route('admin.store.shipping-rates.update', $rate) : route('admin.store.shipping-rates.store') }}" class="section-card mx-auto grid max-w-3xl gap-4 md:grid-cols-2">
        @csrf
        @if($rate->exists) @method('put') @endif
        <div>
            <label class="form-label">Alcance</label>
            <select class="form-input" name="scope" required>
                @foreach($scopes as $value => $label)
                    <option value="{{ $value }}" @selected(old('scope', $rate->scope) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Monto</label>
            <input class="form-input" type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $rate->amount ?? 0) }}" required>
        </div>
        <div>
            <label class="form-label">Departamento</label>
            <input class="form-input" name="department" value="{{ old('department', $rate->department) }}">
        </div>
        <div>
            <label class="form-label">Ciudad</label>
            <input class="form-input" name="city" value="{{ old('city', $rate->city) }}">
        </div>
        <div>
            <label class="form-label">Zona</label>
            <input class="form-input" name="zone" value="{{ old('zone', $rate->zone) }}">
        </div>
        <div>
            <label class="form-label">Prioridad</label>
            <input class="form-input" type="number" min="0" max="9999" name="priority" value="{{ old('priority', $rate->priority ?? 0) }}" required>
        </div>
        <label class="flex items-center gap-2 md:col-span-2"><input type="checkbox" name="active" value="1" @checked(old('active', $rate->active ?? true))> Activa</label>
        <div class="flex gap-3 md:col-span-2">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('admin.store.shipping-rates.index') }}">Volver</a>
        </div>
    </form>
    @if($rate->exists)
        <form class="mx-auto mt-4 max-w-3xl" method="post" action="{{ route('admin.store.shipping-rates.destroy', $rate) }}">
            @csrf @method('delete')
            <button class="btn-danger" onclick="return confirm('Eliminar esta tarifa?')">Eliminar tarifa</button>
        </form>
    @endif
</x-layouts.app>
