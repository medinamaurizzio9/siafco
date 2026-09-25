<x-layouts.app title="Revisión de solicitud">
    <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <section class="section-card">
            @php($photoPath = $application->affiliate?->photo_path ?? $application->person?->photo)
            <div class="flex gap-4"><div class="h-32 w-28 overflow-hidden border bg-slate-100">@if($photoPath)<img class="h-full w-full object-cover" src="{{ Storage::disk('public')->url($photoPath) }}" alt="Fotografía">@else<div class="grid h-full place-items-center text-2xl font-black text-slate-400">{{ mb_substr($application->person?->full_name ?? 'S', 0, 1) }}</div>@endif</div>
            <div><p class="text-sm font-bold text-slate-500">{{ $application->request_code }}</p><h2 class="text-xl font-black">{{ $application->person?->full_name ?? 'Persona no asociada' }}</h2><p>CI {{ $application->person?->ci ?? 'No registrado' }} {{ $application->person?->ci_complement }}</p><p>{{ $application->person?->phone }}</p><p>{{ $application->person?->email }}</p></div></div>
            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <dt class="font-bold">Sector</dt><dd>{{ $application->sector?->name ?? 'Sin sector' }}</dd><dt class="font-bold">Plan</dt><dd>{{ $application->plan?->name ?? 'Sin plan' }}</dd>
                <dt class="font-bold">Esperado</dt><dd>BOB {{ number_format($application->amount_due,2) }}</dd><dt class="font-bold">Estado</dt><dd><x-affiliation-status :status="$application->status" size="sm" /></dd>
            </dl>
        </section>
        <section class="section-card">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-black">Pago presentado</h2>
                @if($canLoadPayment)
                    <button type="button" class="btn-primary" onclick="document.getElementById('assisted-payment-dialog').showModal()">
                        {{ $application->payment ? '+ Cargar nuevo intento' : '+ Cargar pago' }}
                    </button>
                @endif
            </div>
            @if($application->payment)
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <dt class="font-bold">Método</dt><dd>{{ ucfirst($application->payment->payment_method ?: 'No indicado') }}</dd>
                    <dt class="font-bold">Monto</dt><dd>BOB {{ number_format($application->payment->paid_amount,2) }}</dd>
                    @if(\App\Support\PaymentMethodPresenter::showsTransactionNumber($application->payment->payment_method))
                        <dt class="font-bold">N.º de transacción</dt><dd>{{ $application->payment->reference_number ?: 'No registrado' }}</dd>
                    @endif
                    <dt class="font-bold">Fecha</dt><dd>{{ \App\Support\SiafcoDate::date($application->payment->payment_date) }}</dd>
                    <dt class="font-bold">Registrado por</dt><dd>{{ $application->payment->registrar?->name ?? 'No disponible' }}</dd>
                    <dt class="font-bold">Estado</dt><dd><x-payment-status :status="$application->payment->status" size="sm" /></dd>
                </dl>
                <div class="mt-5 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h3 class="font-black text-[#0b1f3a]">Documentos del pago</h3>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="flex items-start gap-3">
                                <x-ui.icon name="file-text" class="mt-1 h-5 w-5 text-siafco-primary-900" />
                                <div class="min-w-0">
                                    <h4 class="font-black text-slate-900">Comprobante presentado</h4>
                                    <p class="text-sm text-slate-600">Archivo presentado para validar el pago</p>
                                </div>
                            </div>
                            @if($application->payment->voucher_path)
                                <a class="btn-secondary mt-4 min-h-12 w-full" href="{{ route('public-affiliation.admin.receipt',$application->payment) }}">
                                    <x-ui.icon name="file-text" class="h-4 w-4" /> Descargar comprobante
                                </a>
                            @else
                                <p class="mt-4 text-sm font-bold text-slate-600">No adjunto</p>
                            @endif
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-white p-4">
                            <div class="flex items-start gap-3">
                                <x-ui.icon name="receipt" class="mt-1 h-5 w-5 text-siafco-primary-900" />
                                <div class="min-w-0">
                                    <h4 class="font-black text-slate-900">Recibo de pago</h4>
                                    <p class="break-all text-sm font-bold text-slate-700">{{ $application->payment->receipt_number ?: 'Sin número de recibo' }}</p>
                                </div>
                            </div>
                            <dl class="mt-4 grid gap-2 text-sm">
                                @if($application->affiliate?->registration_number)
                                    <div><dt class="font-bold text-slate-500">Registro definitivo</dt><dd class="font-black">{{ $application->affiliate->registration_number }}</dd></div>
                                @else
                                    <div><dt class="font-bold text-slate-500">Registro</dt><dd class="font-black">PENDIENTE DE APROBACIÓN</dd></div>
                                @endif
                            </dl>
                            @if($application->payment->canRenderReceipt())
                                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                                    <a class="btn-secondary min-h-12 w-full" href="{{ route('admin.payments.receipt',$application->payment) }}" target="_blank"><x-ui.icon name="eye" class="h-4 w-4" /> Ver recibo</a>
                                    <a class="btn-primary min-h-12 w-full" href="{{ route('admin.payments.receipt',$application->payment) }}" target="_blank"><x-ui.icon name="receipt" class="h-4 w-4" /> Imprimir</a>
                                </div>
                            @else
                                <p class="mt-4 text-sm font-bold text-slate-600">No disponible</p>
                            @endif
                        </div>
                    </div>
                </div>
                @if($duplicates->isNotEmpty())<div class="mt-4 border border-amber-300 bg-amber-50 p-3 text-amber-900"><strong>Posible duplicado:</strong> esta transacción aparece en {{ $duplicates->count() }} pago(s) adicional(es). Revise manualmente.</div>@endif
                @if($canConfirmPayment)
                    <div class="mt-6 flex flex-wrap gap-3">
                    <form method="post" action="{{ route('public-affiliation.admin.approve',$application->payment) }}" data-confirm-title="Confirmar pago" data-confirm-message="El pago será confirmado y se aplicarán las reglas de activación de la afiliación." data-confirm-accept="Confirmar pago" data-confirm-variant="warning">@csrf<button class="btn-primary">Confirmar pago</button></form>
                    </div>
                @endif
                @if($canRejectPayment)
                <form class="mt-5 border-t pt-5" method="post" action="{{ route('public-affiliation.admin.reject',$application->payment) }}" data-confirm-title="Rechazar pago" data-confirm-message="El pago será rechazado con el motivo indicado." data-confirm-accept="Rechazar pago" data-confirm-variant="danger">@csrf<label><span class="form-label">Motivo de rechazo o corrección</span><textarea class="form-input" name="rejection_reason" required></textarea></label><button class="btn-danger mt-3">Rechazar pago</button></form>
                @endif
            @else<p class="mt-4 text-slate-600">La persona todavía no registró un pago.</p>@endif
        </section>
    </div>

    @if($canLoadPayment)
        <dialog id="assisted-payment-dialog" role="dialog" aria-modal="true" aria-labelledby="assisted-payment-title" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl p-0 shadow-2xl backdrop:bg-black/60">
            <form method="post" action="{{ route('public-affiliation.admin.payment.store', $application) }}" enctype="multipart/form-data" class="space-y-4 p-6" onsubmit="this.querySelector('[type=submit]').disabled = true">
                @csrf
                <div class="flex items-start justify-between gap-4">
                    <div><h2 id="assisted-payment-title" class="text-xl font-black">Cargar pago</h2><p class="text-sm text-slate-600">La operación quedará en revisión antes de confirmar la afiliación.</p></div>
                    <button type="button" class="text-2xl leading-none" aria-label="Cerrar" onclick="this.closest('dialog').close()">&times;</button>
                </div>
                @if($errors->any())<div class="rounded-lg border border-red-300 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div>@endif
                <div><label for="assisted-payment-method"><span class="form-label">Método de pago</span></label><select class="form-input" name="payment_method" id="assisted-payment-method" required autofocus><option value="efectivo" @selected(old('payment_method', 'efectivo') === 'efectivo')>Efectivo</option><option value="qr" @selected(old('payment_method') === 'qr')>QR</option><option value="transferencia" @selected(old('payment_method') === 'transferencia')>Transferencia</option></select></div>
                <div><label for="assisted-payment-amount"><span class="form-label">Monto</span></label><input class="form-input" id="assisted-payment-amount" name="amount" value="{{ old('amount', number_format((float) $application->amount_due, 2, '.', '')) }}" readonly></div>
                <div id="assisted-payment-reference-group" hidden>
                    <label for="assisted-payment-reference"><span class="form-label">Número de transacción</span></label>
                    <input class="form-input" id="assisted-payment-reference" name="reference_number" value="{{ old('reference_number') }}" maxlength="120" placeholder="Ingrese el número de transacción" aria-describedby="assisted-payment-reference-error">
                    @error('reference_number')<span id="assisted-payment-reference-error" class="mt-1 block text-sm text-red-700">{{ $message }}</span>@enderror
                </div>
                <div><label for="assisted-payment-voucher"><span class="form-label">Comprobante (opcional)</span></label><input class="form-input" id="assisted-payment-voucher" type="file" name="voucher" accept="image/jpeg,image/png,image/webp,application/pdf"></div>
                <div><label for="assisted-payment-observations"><span class="form-label">Observación (opcional)</span></label><textarea class="form-input" id="assisted-payment-observations" name="observations" maxlength="500">{{ old('observations') }}</textarea></div>
                <div class="flex justify-end gap-3"><button type="button" class="btn-secondary" onclick="this.closest('dialog').close()">Cancelar</button><button type="submit" class="btn-primary">Registrar pago</button></div>
            </form>
        </dialog>
        <script>
            (() => {
                const dialog = document.getElementById('assisted-payment-dialog');
                const method = document.getElementById('assisted-payment-method');
                const referenceGroup = document.getElementById('assisted-payment-reference-group');
                const reference = document.getElementById('assisted-payment-reference');
                const syncReference = () => {
                    const requiresReference = method.value === 'qr' || method.value === 'transferencia';
                    referenceGroup.hidden = !requiresReference;
                    reference.required = requiresReference;
                    if (!requiresReference) reference.value = '';
                };
                method.addEventListener('change', syncReference);
                syncReference();
                @if($errors->any() || request()->boolean('cargar_pago')) dialog.showModal(); @endif
            })();
        </script>
    @endif
</x-layouts.app>
