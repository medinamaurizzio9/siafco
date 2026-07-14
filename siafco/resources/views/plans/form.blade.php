<x-layouts.app title="{{ $plan->exists ? 'Editar plan' : 'Nuevo plan' }}">
    <form method="post" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}" class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2">
        @csrf
        @if($plan->exists) @method('put') @endif
        <div>
            <label class="form-label">Nombre</label>
            <input class="form-input" name="name" value="{{ old('name', $plan->name) }}" required>
        </div>
        <div>
            <label class="form-label">Afiliacion Bs</label>
            <input class="form-input" type="number" step="0.01" name="affiliation_fee" value="{{ old('affiliation_fee', $plan->affiliation_fee) }}" required>
        </div>
        <div>
            <label class="form-label">Credencial Bs</label>
            <input class="form-input" type="number" step="0.01" name="credential_fee" value="{{ old('credential_fee', $plan->credential_fee) }}" required>
        </div>
        <div>
            <label class="form-label">Descripcion</label>
            <input class="form-input" name="description" value="{{ old('description', $plan->description) }}">
        </div>
        <label class="flex items-center gap-2 md:col-span-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))>
            Activo
        </label>
        <div class="flex gap-3 md:col-span-2">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('plans.index') }}">Volver</a>
        </div>
    </form>
</x-layouts.app>
