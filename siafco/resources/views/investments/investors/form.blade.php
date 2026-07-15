<x-layouts.app title="{{ $investor->exists ? 'Editar accionista' : 'Nuevo accionista' }}">
    <form class="section-card grid gap-4 md:grid-cols-2" method="post" enctype="multipart/form-data" action="{{ $investor->exists ? route('investments.investors.update', $investor) : route('investments.investors.store') }}">
        @csrf
        @if($investor->exists) @method('put') @endif
        <div>
            <label class="form-label">Nombre completo</label>
            <input class="form-input" name="full_name" value="{{ old('full_name', $person->full_name) }}" required>
        </div>
        <div>
            <label class="form-label">CI</label>
            <input class="form-input" name="ci" value="{{ old('ci', $person->ci) }}" @readonly($investor->exists) required>
        </div>
        <div>
            <label class="form-label">Complemento</label>
            <input class="form-input" name="ci_complement" value="{{ old('ci_complement', $person->ci_complement) }}">
        </div>
        <div>
            <label class="form-label">Expedido en</label>
            <input class="form-input" name="issued_in" value="{{ old('issued_in', $person->issued_in) }}">
        </div>
        <div>
            <label class="form-label">Celular</label>
            <input class="form-input" name="phone" value="{{ old('phone', $person->phone) }}">
        </div>
        <div>
            <label class="form-label">Correo</label>
            <input class="form-input" type="email" name="email" value="{{ old('email', $person->email) }}">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Direccion</label>
            <input class="form-input" name="address" value="{{ old('address', $person->address) }}">
        </div>
        <div>
            <label class="form-label">Fecha nacimiento</label>
            <input class="form-input" type="date" name="birth_date" value="{{ old('birth_date', optional($person->birth_date)->format('Y-m-d')) }}">
        </div>
        <div>
            <label class="form-label">Estado civil</label>
            <input class="form-input" name="marital_status" value="{{ old('marital_status', $person->marital_status) }}">
        </div>
        <div>
            <label class="form-label">Estado accionista</label>
            <select class="form-input" name="status">
                @foreach(['prospect','reserved','active','suspended','completed','cancelled'] as $status)
                    <option value="{{ $status }}" @selected(old('status', $investor->status ?: 'prospect') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Fecha inicio</label>
            <input class="form-input" type="date" name="start_date" value="{{ old('start_date', optional($investor->start_date)->format('Y-m-d')) }}">
        </div>
        <div>
            <label class="form-label">Foto</label>
            <input class="form-input" type="file" name="photo" accept="image/*">
        </div>
        <div class="md:col-span-2">
            <label class="form-label">Notas</label>
            <textarea class="form-input" name="notes" rows="3">{{ old('notes', $investor->notes) }}</textarea>
        </div>
        <div class="md:col-span-2 flex gap-3">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('investments.investors.index') }}">Cancelar</a>
        </div>
    </form>
</x-layouts.app>
