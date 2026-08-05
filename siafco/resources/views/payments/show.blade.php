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
                <div><dt class="text-xs font-black uppercase text-slate-500">Estado</dt><dd><x-affiliation-status :status="$payment->status" size="sm" /></dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Metodo</dt><dd>{{ ucfirst((string) $payment->payment_method) }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Referencia</dt><dd>{{ $payment->reference_number ?: 'Sin referencia' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Transaccion</dt><dd>{{ $payment->transaction_number ?: 'Sin transaccion' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Pago realizado</dt><dd>{{ $payment->paid_at?->format('d/m/Y H:i') ?? $payment->payment_date?->format('d/m/Y') ?? 'Sin fecha' }}</dd></div>
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
                <h3 class="font-black text-[#0b1f3a]">Acciones</h3>
                <div class="mt-4 grid gap-2">
                    @if(auth()->user()->hasPermission('payments.update_pending') && \App\Support\PaymentStatus::isEditable($payment->status))
                        <a class="btn-secondary" href="{{ route('payments.edit', $payment) }}">Editar pendiente</a>
                    @endif
                    @if(auth()->user()->hasPermission('payments.confirm') && \App\Support\PaymentStatus::isEditable($payment->status))
                        <form method="post" action="{{ route('payments.confirm', $payment) }}">@csrf<button class="btn-primary w-full">Confirmar pago</button></form>
                    @endif
                    @if(auth()->user()->hasPermission('payments.reject') && ! \App\Support\PaymentStatus::isConfirmed($payment->status) && ! \App\Support\PaymentStatus::isVoided($payment->status))
                        <form class="grid gap-2" method="post" action="{{ route('payments.reject', $payment) }}">@csrf<textarea class="form-input" name="rejection_reason" placeholder="Motivo de rechazo" required></textarea><button class="btn-danger">Rechazar</button></form>
                    @endif
                    @if(auth()->user()->hasPermission('payments.void') && \App\Support\PaymentStatus::isConfirmed($payment->status))
                        <form class="grid gap-2 rounded border border-red-200 bg-red-50 p-3" method="post" action="{{ route('payments.void', $payment) }}">@csrf<input class="form-input" name="confirmation" placeholder="Escriba ANULAR" required><textarea class="form-input" name="void_reason" placeholder="Motivo de anulacion" required></textarea><button class="btn-danger">Anular pago</button></form>
                    @endif
                    @if(auth()->user()->hasPermission('payments.view_receipt') && $payment->voucher_path)
                        <a class="btn-secondary" href="{{ route('payments.voucher', $payment) }}" target="_blank">Ver comprobante</a>
                    @endif
                    @if(auth()->user()->hasPermission('payments.view_receipt'))
                        <a class="btn-secondary" href="{{ route('payments.receipt', $payment) }}" target="_blank">Ver recibo PDF</a>
                    @endif
                    @if(auth()->user()->hasPermission('payments.download_receipt'))
                        <a class="btn-secondary" href="{{ route('payments.receipt.download', $payment) }}">Descargar recibo</a>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-layouts.app>
