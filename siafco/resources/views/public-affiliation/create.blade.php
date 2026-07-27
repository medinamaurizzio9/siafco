<x-layouts.app title="Formulario de afiliación">
    <form method="post" action="{{ route('public-affiliation.store') }}" enctype="multipart/form-data" class="mx-auto max-w-4xl space-y-6">
        @csrf
        <div class="flex items-center justify-between border-b border-slate-300 pb-3">
            <h1 class="text-2xl font-black text-[#0b1f3a]">Solicitud de afiliación</h1>
            <span class="text-sm font-bold text-slate-500">Paso 1 de 3</span>
        </div>
        <fieldset class="section-card grid gap-4 sm:grid-cols-2">
            <legend class="mb-3 text-lg font-black text-[#0b1f3a]">Datos personales</legend>
            @foreach([
                ['full_name','Nombre completo','text'], ['ci','Cédula de identidad','text'],
                ['ci_complement','Complemento (opcional)','text'], ['issued_in','Lugar de expedición','text'],
                ['phone','Celular','tel'], ['email','Correo electrónico','email'],
                ['address','Dirección','text'], ['birth_date','Fecha de nacimiento','date'],
                ['marital_status','Estado civil','text'], ['position','Cargo o profesión','text'],
                ['regional','Regional','text'], ['institution','Institución','text'],
            ] as [$name,$label,$type])
                <label><span class="form-label">{{ $label }}</span><input class="form-input" type="{{ $type }}" name="{{ $name }}" value="{{ old($name) }}" @if(in_array($name, ['full_name','ci_complement','issued_in','address','marital_status','position','regional','institution'])) data-uppercase @endif {{ $name === 'ci_complement' ? '' : 'required' }}></label>
            @endforeach
            <x-password-input name="password" label="Contraseña de acceso" autocomplete="new-password" />
            <x-password-input name="password_confirmation" label="Confirmar contraseña" autocomplete="new-password" />
            <label class="sm:col-span-2"><span class="form-label">Fotografía</span><input class="form-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="user" required></label>
        </fieldset>
        <fieldset class="section-card grid gap-4 sm:grid-cols-2">
            <legend class="mb-3 text-lg font-black text-[#0b1f3a]">Sector y plan</legend>
            <label><span class="form-label">Sector</span><select class="form-input" name="sector_id" required><option value="">Seleccione</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected(old('sector_id') == $sector->id)>{{ $sector->name }}</option>@endforeach</select></label>
            <label><span class="form-label">Plan</span><select class="form-input" name="affiliation_plan_id" data-plan-select required><option value="">Seleccione</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" data-sector="{{ $plan->sector_id }}" data-amount="{{ number_format($plan->total_amount, 2, '.', '') }}" @selected(old('affiliation_plan_id') == $plan->id)>{{ $plan->name }} — {{ $plan->currency }} {{ number_format($plan->total_amount, 2) }}</option>@endforeach</select></label>
            <p class="sm:col-span-2 border-l-4 border-[#d4af37] bg-slate-50 p-4">Monto del plan: <strong data-plan-amount>Seleccione un plan</strong></p>
        </fieldset>
        <div class="section-card space-y-3">
            <label class="flex gap-3"><input type="checkbox" name="terms" value="1" required><span>Acepto los términos de afiliación.</span></label>
            <label class="flex gap-3"><input type="checkbox" name="data_processing" value="1" required><span>Acepto el tratamiento de mis datos para gestionar la afiliación.</span></label>
        </div>
        <button class="btn-primary min-h-12 w-full sm:w-auto">Continuar al pago</button>
    </form>
    @push('scripts')
    <script>
        const sector = document.querySelector('[name="sector_id"]');
        const plan = document.querySelector('[data-plan-select]');
        const amount = document.querySelector('[data-plan-amount]');
        function syncPlans() {
            [...plan.options].forEach(option => option.hidden = option.dataset.sector && option.dataset.sector !== sector.value);
            if (plan.selectedOptions[0]?.hidden) plan.value = '';
            amount.textContent = plan.selectedOptions[0]?.dataset.amount ? `BOB ${plan.selectedOptions[0].dataset.amount}` : 'Seleccione un plan';
        }
        sector.addEventListener('change', syncPlans); plan.addEventListener('change', syncPlans); syncPlans();
    </script>
    @endpush
</x-layouts.app>
