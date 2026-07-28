<x-layouts.app title="Revisión de solicitud">
    <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
        <section class="section-card">
            <div class="flex gap-4"><div class="h-32 w-28 overflow-hidden border bg-slate-100">@if($application->affiliate->photo_path)<img class="h-full w-full object-cover" src="{{ Storage::disk('public')->url($application->affiliate->photo_path) }}" alt="Fotografía">@endif</div>
            <div><p class="text-sm font-bold text-slate-500">{{ $application->request_code }}</p><h2 class="text-xl font-black">{{ $application->person->full_name }}</h2><p>CI {{ $application->person->ci }} {{ $application->person->ci_complement }}</p><p>{{ $application->person->phone }}</p><p>{{ $application->person->email }}</p></div></div>
            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <dt class="font-bold">Sector</dt><dd>{{ $application->sector->name }}</dd><dt class="font-bold">Plan</dt><dd>{{ $application->plan->name }}</dd>
                <dt class="font-bold">Esperado</dt><dd>BOB {{ number_format($application->amount_due,2) }}</dd><dt class="font-bold">Estado</dt><dd><x-affiliation-status :status="$application->status" size="sm" /></dd>
            </dl>
        </section>
        <section class="section-card">
            <h2 class="text-xl font-black">Pago presentado</h2>
            @if($application->payment)
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <dt class="font-bold">Transacción</dt><dd>{{ $application->payment->transaction_number }}</dd><dt class="font-bold">Monto declarado</dt><dd>BOB {{ number_format($application->payment->paid_amount,2) }}</dd>
                    <dt class="font-bold">Fecha</dt><dd>{{ $application->payment->payment_date?->format('d/m/Y') }}</dd><dt class="font-bold">Banco</dt><dd>{{ $application->payment->bank_name ?: 'No indicado' }}</dd>
                    <dt class="font-bold">Pagador</dt><dd>{{ $application->payment->payer_name }}</dd><dt class="font-bold">Comprobante</dt><dd>@if($application->payment->voucher_path)<a class="font-bold text-blue-700 underline" href="{{ route('public-affiliation.admin.receipt',$application->payment) }}">Descargar</a>@else No adjunto @endif</dd>
                </dl>
                @if($duplicates->isNotEmpty())<div class="mt-4 border border-amber-300 bg-amber-50 p-3 text-amber-900"><strong>Posible duplicado:</strong> esta transacción aparece en {{ $duplicates->count() }} pago(s) adicional(es). Revise manualmente.</div>@endif
                <div class="mt-6 flex flex-wrap gap-3">
                    <form method="post" action="{{ route('public-affiliation.admin.take',$application) }}">@csrf<button class="btn-secondary">Tomar para revisión</button></form>
                    <form method="post" action="{{ route('public-affiliation.admin.approve',$application->payment) }}">@csrf<button class="btn-primary">Confirmar pago</button></form>
                </div>
                <form class="mt-5 border-t pt-5" method="post" action="{{ route('public-affiliation.admin.reject',$application->payment) }}">@csrf<label><span class="form-label">Motivo de rechazo o corrección</span><textarea class="form-input" name="rejection_reason" required></textarea></label><button class="btn-danger mt-3">Rechazar pago</button></form>
            @else<p class="mt-4 text-slate-600">La persona todavía no registró una transferencia.</p>@endif
        </section>
    </div>
</x-layouts.app>
