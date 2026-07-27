<x-layouts.app title="Pago de afiliación">
    <div class="mx-auto grid max-w-5xl gap-6 lg:grid-cols-[1fr_1.3fr]">
        <section class="section-card">
            <p class="text-sm font-bold text-slate-500">{{ $application->request_code }}</p>
            <h1 class="mt-1 text-2xl font-black text-[#0b1f3a]">Realiza la transferencia</h1>
            <img class="mx-auto mt-5 w-full max-w-sm border border-slate-200" src="{{ $institution->paymentQrUrl() }}" alt="QR bancario institucional">
            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <dt class="font-bold">Monto exacto</dt><dd>BOB {{ number_format($application->amount_due, 2) }}</dd>
                <dt class="font-bold">Banco</dt><dd>{{ $institution->payment_bank ?: 'Consulte con Secretaría' }}</dd>
                <dt class="font-bold">Titular</dt><dd>{{ $institution->payment_holder ?: $institution->institution_name }}</dd>
                <dt class="font-bold">Cuenta</dt><dd>{{ $institution->payment_account ?: 'No informada' }}</dd>
                <dt class="font-bold">Referencia</dt><dd>{{ $application->request_code }}</dd>
            </dl>
            <p class="mt-4 text-sm text-slate-600">{{ $application->plan->payment_instructions ?: $institution->payment_instructions }}</p>
        </section>
        <form class="section-card space-y-4" method="post" action="{{ route('public-affiliation.payment.store', $application) }}" enctype="multipart/form-data">
            @csrf
            <div><p class="text-sm font-bold text-slate-500">Paso 2 de 3</p><h2 class="text-xl font-black">Registra tu transferencia</h2></div>
            @foreach([['transaction_number','Número de transacción','text'],['payment_date','Fecha de pago','date'],['bank_name','Banco de origen (opcional)','text'],['payer_name','Nombre del pagador','text'],['paid_amount','Monto pagado','number']] as [$name,$label,$type])
                <label><span class="form-label">{{ $label }}</span><input class="form-input" name="{{ $name }}" type="{{ $type }}" @if($name === 'paid_amount') step="0.01" value="{{ old($name, $application->amount_due) }}" @else value="{{ old($name, $application->payment?->$name) }}" @endif {{ $name === 'bank_name' ? '' : 'required' }}></label>
            @endforeach
            <label><span class="form-label">Comprobante (opcional)</span><input class="form-input" type="file" name="receipt" accept="image/jpeg,image/png,image/webp,application/pdf" capture="environment"></label>
            <label><span class="form-label">Observaciones (opcional)</span><textarea class="form-input" name="observations" rows="3">{{ old('observations') }}</textarea></label>
            <p class="text-sm text-slate-600">Registrar la transacción no confirma el pago. Secretaría hará una revisión manual.</p>
            <button class="btn-primary min-h-12 w-full">Enviar pago a revisión</button>
        </form>
    </div>
</x-layouts.app>
