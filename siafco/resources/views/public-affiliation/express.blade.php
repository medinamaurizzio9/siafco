<x-layouts.app title="Crear afiliación">
    <div class="express-shell">
        <form method="post" action="{{ route('public-affiliation.express.store') }}" enctype="multipart/form-data" class="express-card" data-express-form>
            @csrf
            <header class="express-hero">
                <a href="{{ route('login') }}" aria-label="Volver a iniciar sesión">←</a>
                <div>
                    <p>SIAFCO</p>
                    <h1>Crear afiliación</h1>
                    <span>Te tomará pocos minutos. Completarás el resto desde tu panel.</span>
                </div>
            </header>

            <ol class="express-steps" aria-label="Pasos de afiliación">
                <li class="is-active" data-step-dot="1"><span>1</span><strong>Datos</strong></li>
                <li data-step-dot="2"><span>2</span><strong>Pago</strong></li>
                <li><span>3</span><strong>Acceso</strong></li>
            </ol>

            @if($errors->any())
                <div class="alert alert-danger" role="alert">
                    <strong>Revisa los datos ingresados.</strong>
                    <p class="mt-1">{{ $errors->first() }}</p>
                </div>
            @endif

            <section class="express-step" data-step-panel="1">
                <label><span class="form-label">Nombre completo</span><input class="form-input" name="full_name" value="{{ old('full_name') }}" autocomplete="name" required data-uppercase></label>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label><span class="form-label">Cédula de identidad</span><input class="form-input" name="ci" value="{{ old('ci') }}" inputmode="numeric" maxlength="30" required></label>
                    <label><span class="form-label">Lugar de expedición</span><select class="form-input" name="issued_in" required><option value="">Seleccione</option>@foreach($expeditionPlaces as $value => $label)<option value="{{ $value }}" @selected(old('issued_in') === $value)>{{ $label }}</option>@endforeach</select></label>
                </div>
                <label><span class="form-label">Celular</span><input class="form-input" name="phone" value="{{ old('phone') }}" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" autocomplete="tel" required data-express-phone></label>
                <label><span class="form-label">Sector</span><select class="form-input" name="sector_id" required data-express-sector><option value="">Seleccione un sector</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected(old('sector_id') == $sector->id)>{{ $sector->name }}</option>@endforeach</select></label>
                <label><span class="form-label">Plan</span><select class="form-input" name="affiliation_plan_id" required data-express-plan><option value="">Seleccione primero un sector</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" data-sector="{{ $plan->sector_id }}" data-amount="{{ number_format($plan->total_amount, 2, '.', '') }}" data-currency="{{ $plan->currency }}" @selected(old('affiliation_plan_id') == $plan->id)>{{ $plan->name }}</option>@endforeach</select></label>
                <div class="express-amount"><span>Monto de afiliación</span><strong data-express-amount>Seleccione un plan</strong></div>
                <button class="btn-primary min-h-12 w-full" type="button" data-next-step>Continuar</button>
            </section>

            <section class="express-step" data-step-panel="2" hidden>
                <h2 class="text-xl font-black text-siafco-primary-900">Realiza tu pago</h2>
                <div class="express-payment-box">
                    <span>Monto de afiliación</span>
                    <strong data-express-amount-copy>Bs 0.00</strong>
                    @if($institution->paymentQrUrl())
                        <img src="{{ $institution->paymentQrUrl() }}" alt="QR institucional de pago">
                    @else
                        <p>No existe un QR institucional configurado. Comunícate con Secretaría.</p>
                    @endif
                    <small>Escanea el código desde tu aplicación bancaria y registra los datos del pago.</small>
                </div>
                <label><span class="form-label">N.º transacción</span><input class="form-input" name="transaction_number" value="{{ old('transaction_number') }}" maxlength="120" required></label>
                <label><span class="form-label">Banco</span><input class="form-input" name="bank_name" value="{{ old('bank_name') }}" maxlength="120" required data-uppercase></label>
                <label><span class="form-label">Pago realizado por</span><input class="form-input" name="payer_name" value="{{ old('payer_name', old('full_name')) }}" maxlength="255" required data-uppercase><small>Indica el nombre de la persona desde cuya cuenta se realizó el pago.</small></label>
                <label><span class="form-label">Fecha de pago</span><input class="form-input" type="date" name="payment_date" value="{{ old('payment_date', $today) }}" max="{{ $today }}" required></label>
                <label><span class="form-label">Comprobante (opcional)</span><input class="form-input" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
                <label><span class="form-label">Observaciones (opcional)</span><textarea class="form-input" name="observations" rows="3" maxlength="1000" data-uppercase>{{ old('observations') }}</textarea></label>
                <div class="express-review">
                    <h2>Resumen</h2>
                    <dl>
                        <div><dt>Nombre</dt><dd data-summary="full_name">-</dd></div>
                        <div><dt>CI</dt><dd data-summary="ci">-</dd></div>
                        <div><dt>Sector</dt><dd data-summary="sector">-</dd></div>
                        <div><dt>Plan</dt><dd data-summary="plan">-</dd></div>
                        <div><dt>Monto</dt><dd data-summary="amount">-</dd></div>
                        <div><dt>Transacción</dt><dd data-summary="transaction_number">-</dd></div>
                        <div><dt>Banco</dt><dd data-summary="bank_name">-</dd></div>
                        <div><dt>Fecha</dt><dd data-summary="payment_date">-</dd></div>
                    </dl>
                </div>
                <div class="grid gap-2 sm:grid-cols-2">
                    <button class="btn-secondary min-h-12 w-full" type="button" data-prev-step>Volver</button>
                    <button class="btn-primary min-h-12 w-full" type="submit" data-submit-once>Enviar afiliación</button>
                </div>
            </section>
        </form>
    </div>

    @push('scripts')
        <script>
            (() => {
                const form = document.querySelector('[data-express-form]');
                if (!form) return;
                const step1 = form.querySelector('[data-step-panel="1"]');
                const step2 = form.querySelector('[data-step-panel="2"]');
                const dots = form.querySelectorAll('[data-step-dot]');
                const sector = form.querySelector('[data-express-sector]');
                const plan = form.querySelector('[data-express-plan]');
                const amount = form.querySelector('[data-express-amount]');
                const amountCopy = form.querySelector('[data-express-amount-copy]');
                const submitButton = form.querySelector('[data-submit-once]');
                const allPlans = Array.from(plan.options).map(option => option.cloneNode(true));
                const money = value => value ? `Bs ${Number(value).toFixed(2)}` : 'Seleccione un plan';
                const syncPlans = () => {
                    const selectedSector = sector.value;
                    const previous = plan.value;
                    plan.innerHTML = '';
                    allPlans.forEach(option => {
                        if (!option.value || option.dataset.sector === selectedSector) plan.appendChild(option.cloneNode(true));
                    });
                    if ([...plan.options].some(option => option.value === previous)) plan.value = previous;
                    syncAmount();
                };
                const syncAmount = () => {
                    const selected = plan.selectedOptions[0];
                    const label = money(selected?.dataset.amount);
                    amount.textContent = label;
                    amountCopy.textContent = label;
                };
                const setStep = step => {
                    step1.hidden = step !== 1;
                    step2.hidden = step !== 2;
                    dots.forEach(dot => dot.classList.toggle('is-active', dot.dataset.stepDot === String(step)));
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                };
                const syncSummary = () => {
                    ['full_name', 'ci', 'transaction_number', 'bank_name', 'payment_date'].forEach(name => {
                        form.querySelector(`[data-summary="${name}"]`).textContent = form.elements[name]?.value || '-';
                    });
                    form.querySelector('[data-summary="sector"]').textContent = sector.selectedOptions[0]?.textContent || '-';
                    form.querySelector('[data-summary="plan"]').textContent = plan.selectedOptions[0]?.textContent || '-';
                    form.querySelector('[data-summary="amount"]').textContent = amount.textContent;
                };
                form.querySelector('[data-next-step]')?.addEventListener('click', () => {
                    const fields = step1.querySelectorAll('input, select');
                    if (![...fields].every(field => field.reportValidity())) return;
                    syncSummary();
                    setStep(2);
                });
                form.querySelector('[data-prev-step]')?.addEventListener('click', () => setStep(1));
                form.addEventListener('input', syncSummary);
                form.addEventListener('change', syncSummary);
                form.addEventListener('invalid', event => {
                    submitButton.disabled = false;
                    if (step1.contains(event.target)) setStep(1);
                    if (step2.contains(event.target)) setStep(2);
                }, true);
                form.addEventListener('submit', event => {
                    syncSummary();
                    if (!form.checkValidity()) {
                        submitButton.disabled = false;
                        return;
                    }
                    submitButton.disabled = true;
                });
                form.querySelector('[data-express-phone]')?.addEventListener('input', event => event.target.value = event.target.value.replace(/\D/g, '').slice(0, 8));
                sector.addEventListener('change', syncPlans);
                plan.addEventListener('change', syncAmount);
                syncPlans();
                if (@json($errors->has('transaction_number') || $errors->has('bank_name') || $errors->has('payer_name') || $errors->has('payment_date') || $errors->has('receipt') || $errors->has('observations'))) setStep(2);
            })();
        </script>
    @endpush
</x-layouts.app>
