<x-layouts.app title="Afiliacion registrada">
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">Afiliacion registrada</h2>
            <p class="text-sm text-slate-600">Resultado del registro presencial y estado actual del pago.</p>
        </div>
        <a class="btn-secondary" href="{{ route('affiliates.index') }}">Listado de afiliados</a>
    </div>

    <div class="grid gap-5 xl:grid-cols-[1fr_360px]">
        <section class="rounded-lg border {{ \App\Support\PaymentStatus::isConfirmed($payment->status) ? 'border-emerald-200' : 'border-amber-300' }} bg-white p-5 shadow-sm">
            <div class="mb-5 rounded-lg border {{ \App\Support\PaymentStatus::isConfirmed($payment->status) ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-300 bg-amber-50 text-amber-900' }} p-4">
                <p class="text-xs font-black uppercase tracking-wide">{{ \App\Support\PaymentStatus::isConfirmed($payment->status) ? 'Pago confirmado' : 'Pendiente de verificación de pago' }}</p>
                <p class="mt-1 text-sm">{{ \App\Support\PaymentStatus::isConfirmed($payment->status) ? 'La afiliacion presencial quedo activa segun las reglas de confirmacion de pagos.' : 'El pago fue registrado y debe ser verificado por Gerencia o Administración antes de activar la afiliación.' }}</p>
            </div>

            <dl class="grid gap-4 md:grid-cols-2">
                <div><dt class="text-xs font-black uppercase text-slate-500">Nombre</dt><dd class="font-bold">{{ $affiliate->full_name }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">CI</dt><dd>{{ $affiliate->ci }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Codigo de afiliado</dt><dd class="font-black">{{ $affiliate->registration_number ?: 'Pendiente de aprobación' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Sector</dt><dd>{{ $affiliate->sector?->name ?? 'Sin sector' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Plan</dt><dd>{{ $affiliate->plan?->name ?? $payment->plan?->name ?? 'Sin plan' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Monto pagado</dt><dd>{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Metodo</dt><dd>{{ \App\Support\PaymentMethodPresenter::label($payment->payment_method) }}</dd></div>
                @if(\App\Support\PaymentMethodPresenter::showsTransactionNumber($payment->payment_method))<div><dt class="text-xs font-black uppercase text-slate-500">N.º de transacción</dt><dd>{{ $payment->reference_number }}</dd></div>@endif
                <div><dt class="text-xs font-black uppercase text-slate-500">Estado del pago</dt><dd><x-payment-status :status="$payment->status" size="sm" /></dd></div>
                @php($affiliateDisplayStatus = \App\Support\AffiliationStatusPresenter::forAffiliate($affiliate))
                <div><dt class="text-xs font-black uppercase text-slate-500">Estado de afiliacion</dt><dd><x-affiliation-status :status="$affiliateDisplayStatus" :label="\App\Support\AffiliationStatusPresenter::officeSummaryLabel($affiliateDisplayStatus)" size="sm" /></dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Registrado por</dt><dd>{{ $payment->registrar?->name ?? 'No registrado' }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Fecha</dt><dd>{{ $payment->confirmed_at?->format('d/m/Y H:i') ?? $payment->paid_at?->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Recibo</dt><dd>{{ $payment->receipt_number ?: 'Pendiente' }}</dd></div>
            </dl>
        </section>

        <aside class="grid gap-4 content-start">
            <section class="rounded-lg border border-slate-200 bg-white p-5">
                <h3 class="font-black text-[#0b1f3a]">Acciones</h3>
                <div class="mt-4 grid gap-2">
                    @if(\App\Support\PaymentStatus::isConfirmed($payment->status))
                        <a class="btn-primary" href="{{ route('affiliates.show', $affiliate) }}">Ver afiliado</a>
                    @else
                        <a class="btn-primary" href="{{ route('payments.show', $payment) }}">Ver pago</a>
                    @endif
                    @if(auth()->user()->hasPermission('credentials.view') && $affiliate->credential)
                        <a class="btn-secondary" href="{{ route('credentials.preview', $affiliate) }}" target="_blank">Ver credencial</a>
                    @endif
                    @if(auth()->user()->hasPermission('credentials.print') && $affiliate->credential)
                        <a class="btn-secondary" href="{{ route('credentials.print', $affiliate) }}" target="_blank">Imprimir credencial</a>
                    @endif
                    @if((auth()->user()->hasPermission('payments.view_receipt') || auth()->user()->hasRole('caja')) && \App\Support\PaymentStatus::isConfirmed($payment->status) && $payment->receipt_number)
                        <a class="btn-primary" href="{{ route('admin.payments.receipt', $payment) }}" target="_blank">Ver/Imprimir recibo</a>
                    @endif
                </div>
            </section>
        </aside>
    </div>
</x-layouts.app>
