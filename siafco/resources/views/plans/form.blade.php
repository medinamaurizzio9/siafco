<x-layouts.app title="{{ $plan->exists ? 'Editar plan' : 'Nuevo plan' }}">
    <form method="post" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}" class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2">
        @csrf
        @if($plan->exists) @method('put') @endif
        <div>
            <label class="form-label">Nombre</label>
            <input class="form-input" name="name" value="{{ old('name', $plan->name) }}" required>
        </div>
        <div><label class="form-label">Sector (opcional)</label><select class="form-input" name="sector_id"><option value="">Todos</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected(old('sector_id',$plan->sector_id)==$sector->id)>{{ $sector->name }}</option>@endforeach</select></div>
        <div><label class="form-label">Tipo</label><select class="form-input" name="type" required>@foreach(['convenio'=>'Convenio','alianza'=>'Alianza','independiente'=>'Independiente'] as $value=>$label)<option value="{{ $value }}" @selected(old('type',$plan->type ?: 'independiente')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div><label class="form-label">Moneda</label><input class="form-input" name="currency" maxlength="3" value="{{ old('currency',$plan->currency ?: 'BOB') }}" required></div>
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
        <div><label class="form-label">Vigente desde</label><input class="form-input" type="date" name="valid_from" value="{{ old('valid_from',$plan->valid_from?->format('Y-m-d')) }}"></div>
        <div><label class="form-label">Vigente hasta</label><input class="form-input" type="date" name="valid_until" value="{{ old('valid_until',$plan->valid_until?->format('Y-m-d')) }}"></div>
        <div class="md:col-span-2"><label class="form-label">Instrucciones de pago</label><textarea class="form-input" name="payment_instructions" rows="3">{{ old('payment_instructions',$plan->payment_instructions) }}</textarea></div>
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
