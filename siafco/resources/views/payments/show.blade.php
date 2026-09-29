<x-layouts.app title="Detalle de pago">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Detalle de pago</h2>
            <p class="text-sm text-slate-600">{{ $payment->receipt_number ?: 'Recibo pendiente de confirmacion' }}</p>
        </div>
        <a class="btn-secondary" href="{{ route('payments.index') }}">Volver al listado</a>
    </div>

    <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <dl class="grid gap-4 md:grid-cols-2">
                <div><dt class="text-xs font-black uppercase text-slate-500">Afiliado</dt><dd class="font-bold">{{ $payment->affiliate?->full_name ?? 'Afiliado no disponible' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Codigo</dt><dd>{{ $payment->affiliate?->registration_number ?: 'Sin codigo' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Monto</dt><dd>{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Estado</dt><dd><x-payment-status :status="$payment->status" size="sm" /></dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Metodo</dt><dd>{{ ucfirst((string) $payment->payment_method) }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">N.º de transacción</dt><dd>{{ $payment->transactionNumber() ?: 'No registrado' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Fecha y hora del pago</dt><dd>{{ \App\Support\SiafcoDate::paymentInputDateTime($payment->paid_at ?: $payment->payment_date) }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Registrado en SIAFCO</dt><dd>{{ \App\Support\SiafcoDate::dateTime($payment->created_at) }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Zona horaria declarada</dt><dd>{{ $payment->payment_timezone ?: 'No registrada' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Registrado por</dt><dd>{{ $payment->registrar?->name ?? 'No registrado' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Confirmado por</dt><dd>{{ $payment->cashier?->name ?? 'No confirmado' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Origen</dt><dd>{{ $payment->source ?: 'web' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Recibo</dt><dd>{{ $payment->receipt_number ?: 'Pendiente' }}</dd></div>
            </dl>

            @if($payment->observations)
                <div class="mt-5 rounded border border-slate-200 bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-500">Observaciones</p><p>{{ $payment->observations }}</p></div>
            @endif
            @if($payment->rejection_reason)
                <div class="mt-5 rounded border border-red-200 bg-red-50 p-4"><p class="text-xs font-black uppercase text-red-700">Motivo de rechazo</p><p>{{ $payment->rejection_reason }}</p></div>
            @endif
            @if($payment->void_reason)
                <div class="mt-5 rounded border border-red-200 bg-red-50 p-4"><p class="text-xs font-black uppercase text-red-700">Motivo de anulacion</p><p>{{ $payment->void_reason }}</p></div>
            @endif
        </section>

        <aside class="grid gap-4">
            @if($balance)
                <section class="rounded-lg border border-slate-200 bg-white p-5">
                    <h3 class="font-black text-[#0b1f3a]">Saldo del afiliado</h3>
                    <dl class="mt-4 grid gap-3">
                        <div><dt class="text-xs font-black uppercase text-slate-500">Total plan</dt><dd>BOB {{ number_format($balance['required_amount'], 2) }}</dd></div>
                        <div><dt class="text-xs font-black uppercase text-slate-500">Confirmado</dt><dd>BOB {{ number_format($balance['confirmed_amount'], 2) }}</dd></div>
                        <div><dt class="text-xs font-black uppercase text-slate-500">Saldo</dt><dd>BOB {{ number_format($balance['pending_balance'], 2) }}</dd></div>
                    </dl>
                </section>
            @endif

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h3 class="font-black text-[#0b1f3a]">Comprobante de pago</h3>
                @if($payment->voucher_path)
                    <p class="mt-2 text-sm text-slate-600">Comprobante adjunto.</p>
                    <div class="mt-4 grid gap-2">
                        @if(auth()->user()->hasPermission('payments.view_receipt'))
                            <a class="btn-secondary" href="{{ route('payments.voucher', $payment) }}" target="_blank">Ver comprobante</a>
                        @endif
                        @if(\App\Support\PaymentStatus::isEditable($payment->status) && auth()->user()->isInternal() && auth()->user()->hasRole(['caja','cajero','secretaria','administrador','superadministrador','gerente']))
                            <form class="grid gap-2" method="post" action="{{ route('payments.proof', $payment) }}" enctype="multipart/form-data">@csrf<input class="form-input" type="file" name="voucher" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required><button class="btn-secondary">Reemplazar comprobante</button></form>
                        @endif
                    </div>
                @else
                    <p class="mt-2 text-sm text-slate-600">No se adjuntó comprobante.</p>
                    @if(\App\Support\PaymentStatus::isEditable($payment->status) && auth()->user()->isInternal() && auth()->user()->hasRole(['caja','cajero','secretaria','administrador','superadministrador','gerente']))
                        <form class="mt-4 grid gap-2" method="post" action="{{ route('payments.proof', $payment) }}" enctype="multipart/form-data">@csrf<input class="form-input" type="file" name="voucher" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required><button class="btn-primary">Subir comprobante</button></form>
                    @endif
                @endif
            </section>

            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h3 class="font-black text-[#0b1f3a]">Acciones</h3>
                <div class="mt-4 grid gap-2">
                    @if(auth()->user()->hasPermission('payments.update_pending') && \App\Support\PaymentStatus::isEditable($payment->status))
                        <a class="btn-secondary" href="{{ route('payments.edit', $payment) }}">Editar pendiente</a>
                    @endif
                    @if(app(\App\Services\PaymentActionAuthorization::class)->canConfirm(auth()->user(), $payment))
                        <form method="post" action="{{ route('payments.confirm', $payment) }}" data-confirm-title="{{ $payment->source === 'office_qr' ? 'Confirmar pago QR' : 'Confirmar pago' }}" data-confirm-message="{{ $payment->source === 'office_qr' ? 'Confirme que el pago QR/transferencia por '.($payment->currency ?? 'BOB').' '.number_format((float) ($payment->paid_amount ?? $payment->amount), 2).', operación '.$payment->transactionNumber().', fue verificado correctamente. Afiliado: '.($payment->affiliate?->full_name ?? 'No disponible').'. CI: '.($payment->affiliate?->ci ?? 'No disponible').'. Registrado por: '.($payment->registrar?->name ?? 'No registrado').'.' : 'El pago quedará confirmado y podrá activar la afiliación según el saldo.' }}" data-confirm-accept="Confirmar pago" data-confirm-variant="warning">@csrf<button class="btn-primary w-full">Confirmar pago</button></form>
                    @endif
                    @if(app(\App\Services\PaymentActionAuthorization::class)->canReject(auth()->user(), $payment))
                        <form class="grid gap-2" method="post" action="{{ route('payments.reject', $payment) }}" data-confirm-title="Rechazar pago" data-confirm-message="El pago será rechazado con el motivo indicado." data-confirm-accept="Rechazar pago" data-confirm-variant="danger">@csrf<textarea class="form-input" name="rejection_reason" placeholder="Motivo de rechazo" required></textarea><button class="btn-danger">Rechazar</button></form>
                    @endif
                    @if(auth()->user()->hasPermission('payments.void') && \App\Support\PaymentStatus::isConfirmed($payment->status))
                        <form class="grid gap-2 rounded border border-red-200 bg-red-50 p-3" method="post" action="{{ route('payments.void', $payment) }}" data-confirm-title="Anular pago" data-confirm-message="Esta acción anulará el pago confirmado. Verifique el motivo antes de continuar." data-confirm-accept="Anular pago" data-confirm-variant="danger">@csrf<input class="form-input" name="confirmation" placeholder="Escriba ANULAR" required><textarea class="form-input" name="void_reason" placeholder="Motivo de anulacion" required></textarea><button class="btn-danger">Anular pago</button></form>
                    @endif
                    @if((auth()->user()->hasPermission('payments.view_receipt') || auth()->user()->hasRole('caja')) && $payment->canRenderReceipt())
                        <a class="btn-secondary" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank">Imprimir recibo</a>
                    @endif
                    @if(auth()->user()->hasPermission('payments.download_receipt') && $payment->canRenderReceipt())
                        <a class="btn-secondary" href="{{ route('payments.receipt.download', $payment) }}">Descargar recibo</a>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-layouts.app>
