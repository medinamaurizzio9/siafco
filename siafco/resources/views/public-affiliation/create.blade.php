@php
    $expeditionPlaces = [
        'LP' => 'LP - La Paz', 'CB' => 'CB - Cochabamba', 'SC' => 'SC - Santa Cruz',
        'BN' => 'BN - Beni', 'PA' => 'PA - Pando', 'TR' => 'TR - Tarija',
        'CH' => 'CH - Chuquisaca', 'OR' => 'OR - Oruro', 'PT' => 'PT - Potosí',
    ];
    $maritalStatuses = [
        'SOLTERO' => 'Soltero', 'CASADO' => 'Casado',
        'DIVORCIADO' => 'Divorciado', 'VIUDO' => 'Viudo',
    ];
    $regionals = [
        'LA PAZ' => 'La Paz', 'COCHABAMBA' => 'Cochabamba', 'SANTA CRUZ' => 'Santa Cruz',
        'ORURO' => 'Oruro', 'POTOSÍ' => 'Potosí', 'SUCRE' => 'Sucre',
        'TARIJA' => 'Tarija', 'BENI' => 'Beni', 'PANDO' => 'Pando',
    ];
@endphp

<x-layouts.app title="Formulario de afiliación">
    <form method="post" action="{{ route('public-affiliation.store') }}" enctype="multipart/form-data"
        class="mx-auto max-w-4xl space-y-6" data-public-affiliation-form novalidate>
        @csrf
        <div class="flex items-center justify-between border-b border-slate-300 pb-3">
            <h1 class="text-2xl font-black text-[#0b1f3a]">Solicitud de afiliación</h1>
            <span class="text-sm font-bold text-slate-500">Datos obligatorios</span>
        </div>

        <div class="hidden rounded border border-red-300 bg-red-50 px-4 py-3 text-sm font-bold text-red-800"
            data-validation-summary role="alert" tabindex="-1">
            Completa los campos marcados en rojo para continuar.
        </div>

        <fieldset class="section-card grid gap-4 sm:grid-cols-2">
            <legend class="mb-3 text-lg font-black text-[#0b1f3a]">Datos personales</legend>

            <x-forms.text-input name="full_name" label="Nombre completo" :required="true" :uppercase="true" />
            <x-forms.text-input name="ci" label="Cédula de identidad" :required="true" maxlength="30" />
            <x-forms.text-input name="ci_complement" label="Complemento" :optional="true" :uppercase="true" maxlength="10" />
            <x-forms.select-input name="issued_in" label="Lugar de expedición" :options="$expeditionPlaces"
                :required="true" placeholder="Seleccione una opción" />

            <x-forms.text-input name="phone" label="Celular" type="tel" :required="true"
                inputmode="numeric" pattern="[0-9]{8}" maxlength="8" help="Ingresa 8 dígitos." data-numeric-only />
            <x-forms.text-input name="email" label="Correo electrónico" type="email" :required="true"
                autocomplete="email" />
            <x-password-input name="password" label="Contraseña de acceso" autocomplete="new-password" minlength="8" />
            <x-password-input name="password_confirmation" label="Confirmar contraseña" autocomplete="new-password" minlength="8" />

            <x-forms.text-input name="address" label="Dirección" :required="true" :uppercase="true" />
            <x-forms.text-input name="birth_date" label="Fecha de nacimiento" type="date" :required="true"
                max="{{ today()->subDay()->format('Y-m-d') }}" />
            <x-forms.select-input name="marital_status" label="Estado civil" :options="$maritalStatuses"
                :required="true" placeholder="Seleccione su estado civil" />
            <x-forms.text-input name="position" label="Cargo o profesión" :required="true" :uppercase="true" />
            <x-forms.select-input name="regional" label="Regional" :options="$regionals"
                :required="true" placeholder="Seleccione la regional" />
            <x-forms.text-input name="institution" label="Institución" :required="true" :uppercase="true" />

            <x-forms.photo-cropper />
        </fieldset>

        <fieldset class="section-card grid gap-4 sm:grid-cols-2">
            <legend class="mb-3 text-lg font-black text-[#0b1f3a]">Sector y plan</legend>
            <label for="sector_id" data-field-wrapper>
                <span class="form-label" data-field-label>Sector <span class="text-red-600" aria-hidden="true">*</span></span>
                <select id="sector_id" class="form-input @error('sector_id') border-red-500 bg-red-50 @enderror"
                    name="sector_id" required aria-required="true" aria-invalid="{{ $errors->has('sector_id') ? 'true' : 'false' }}"
                    aria-describedby="sector_id-error" data-validate-field data-touched="{{ $errors->has('sector_id') ? 'true' : 'false' }}">
                    <option value="" disabled @selected(!old('sector_id'))>Seleccione una opción</option>
                    @foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected(old('sector_id') == $sector->id)>{{ $sector->name }}</option>@endforeach
                </select>
                <x-forms.field-error name="sector_id" />
            </label>
            <label for="affiliation_plan_id" data-field-wrapper>
                <span class="form-label" data-field-label>Plan <span class="text-red-600" aria-hidden="true">*</span></span>
                <select id="affiliation_plan_id" class="form-input @error('affiliation_plan_id') border-red-500 bg-red-50 @enderror"
                    name="affiliation_plan_id" data-plan-select required aria-required="true"
                    aria-invalid="{{ $errors->has('affiliation_plan_id') ? 'true' : 'false' }}" aria-describedby="affiliation_plan_id-error"
                    data-validate-field data-touched="{{ $errors->has('affiliation_plan_id') ? 'true' : 'false' }}">
                    <option value="" disabled @selected(!old('affiliation_plan_id'))>Seleccione una opción</option>
                    @foreach($plans as $plan)<option value="{{ $plan->id }}" data-sector="{{ $plan->sector_id }}" data-amount="{{ number_format($plan->total_amount, 2, '.', '') }}" @selected(old('affiliation_plan_id') == $plan->id)>{{ $plan->name }} — {{ $plan->currency }} {{ number_format($plan->total_amount, 2) }}</option>@endforeach
                </select>
                <x-forms.field-error name="affiliation_plan_id" />
            </label>
            <p class="sm:col-span-2 border-l-4 border-[#d4af37] bg-slate-50 p-4">Monto del plan: <strong data-plan-amount>Seleccione un plan</strong></p>
        </fieldset>

        <div class="section-card space-y-3">
            <label class="flex gap-3" data-field-wrapper><input type="checkbox" name="terms" value="1" required aria-required="true" data-validate-field data-touched="false"><span>Acepto los términos de afiliación <span class="text-red-600">*</span></span><x-forms.field-error name="terms" /></label>
            <label class="flex gap-3" data-field-wrapper><input type="checkbox" name="data_processing" value="1" required aria-required="true" data-validate-field data-touched="false"><span>Acepto el tratamiento de mis datos <span class="text-red-600">*</span></span><x-forms.field-error name="data_processing" /></label>
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
        document.querySelector('[data-numeric-only]')?.addEventListener('input', event => event.target.value = event.target.value.replace(/\D/g, '').slice(0, 8));
    </script>
    @endpush
</x-layouts.app>
