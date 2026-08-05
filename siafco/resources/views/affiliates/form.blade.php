<x-layouts.app title="{{ $affiliate->exists ? 'Editar afiliado' : 'Registrar afiliado' }}">
    <form method="post" enctype="multipart/form-data" action="{{ $affiliate->exists ? route('affiliates.update', $affiliate) : route('affiliates.store') }}" class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5 md:grid-cols-2 xl:grid-cols-3">
        @csrf
        @if($affiliate->exists) @method('put') @endif
        <div>
            <label class="form-label">Nombre completo</label>
            <input class="form-input" name="full_name" value="{{ old('full_name', $affiliate->full_name) }}" data-uppercase required>
        </div>
        <div>
            <label class="form-label">CI</label>
            <input class="form-input" name="ci" value="{{ old('ci', $affiliate->ci) }}" required>
        </div>
        <div>
            <label class="form-label">Correo de acceso</label>
            <input class="form-input" type="email" name="email" value="{{ old('email', $affiliate->email) }}" required>
            <p class="mt-1 text-xs text-slate-500">Este correo sera utilizado para iniciar sesion en el portal y en la aplicacion movil.</p>
        </div>
        <div>
            <label class="form-label">Celular</label>
            <input class="form-input" name="phone" value="{{ old('phone', $affiliate->phone) }}">
        </div>
        <div>
            <label class="form-label">Sector</label>
            <select class="form-input" name="sector_id" required>
                @foreach($sectors as $sector)
                    <option value="{{ $sector->id }}" @selected(old('sector_id', $affiliate->sector_id) == $sector->id)>{{ $sector->code }} - {{ $sector->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Plan</label>
            <select class="form-input" name="affiliation_plan_id" required>
                @foreach($plans as $plan)
                    <option value="{{ $plan->id }}" @selected(old('affiliation_plan_id', $affiliate->affiliation_plan_id) == $plan->id)>{{ $plan->name }} - Bs {{ number_format($plan->total_amount, 2) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">Regional</label>
            <input class="form-input" name="regional" value="{{ old('regional', $affiliate->regional) }}" data-uppercase>
        </div>
        <div>
            <label class="form-label">Institucion</label>
            <input class="form-input" name="institution" value="{{ old('institution', $affiliate->institution) }}" data-uppercase>
        </div>
        <div>
            <label class="form-label">Cargo/profesion</label>
            <input class="form-input" name="position" value="{{ old('position', $affiliate->position) }}" data-uppercase>
        </div>
        <div>
            <label class="form-label">Fecha de nacimiento</label>
            <input class="form-input" type="date" name="birth_date" value="{{ old('birth_date', optional($affiliate->birth_date)->format('Y-m-d')) }}">
        </div>
        <div>
            <label class="form-label">Estado civil</label>
            <input class="form-input" name="marital_status" value="{{ old('marital_status', $affiliate->marital_status) }}" data-uppercase>
        </div>
        <div>
            <label class="form-label">Foto</label>
            <input class="form-input" type="file" name="photo" accept="image/*">
        </div>
        <div class="xl:col-span-3">
            <label class="form-label">Direccion</label>
            <input class="form-input" name="address" value="{{ old('address', $affiliate->address) }}" data-uppercase>
        </div>
        @if($affiliate->exists)
            <div>
                <label class="form-label">Estado</label>
                <select class="form-input" name="status">
                    @foreach(['pendiente_pago','activo','inactivo','observado'] as $status)
                        <option value="{{ $status }}" @selected(old('status', $affiliate->status) === $status)>{{ \App\Support\AffiliationStatusPresenter::label($status) }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="flex gap-3 xl:col-span-3">
            <button class="btn-primary">Guardar</button>
            <a class="btn-secondary" href="{{ route('affiliates.index') }}">Volver</a>
        </div>
    </form>
</x-layouts.app>
