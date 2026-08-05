<x-layouts.app title="{{ $payment->exists ? 'Editar pago' : 'Registrar pago' }}">
    <div class="mb-4">
        <h2 class="text-2xl font-black text-[#0b1f3a]">{{ $payment->exists ? 'Editar pago pendiente' : 'Registrar pago manual' }}</h2>
        <p class="text-sm text-slate-600">Los comprobantes se almacenan de forma privada y no se publican como archivos web.</p>
    </div>

    @if($balance)
        <section class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
            <div><p class="text-xs font-black uppercase text-slate-500">Plan</p><p class="font-bold">{{ $affiliate->plan?->name ?? 'Sin plan' }}</p></div>
            <div><p class="text-xs font-black uppercase text-slate-500">Total requerido</p><p class="font-bold">BOB {{ number_format($balance['required_amount'], 2) }}</p></div>
            <div><p class="text-xs font-black uppercase text-slate-500">Confirmado</p><p class="font-bold">BOB {{ number_format($balance['confirmed_amount'], 2) }}</p></div>
            <div><p class="text-xs font-black uppercase text-slate-500">Saldo</p><p class="font-bold">BOB {{ number_format($balance['pending_balance'], 2) }}</p></div>
        </section>
    @endif

    <form class="grid gap-4 rounded-lg border border-slate-200 bg-white p-5" method="post" enctype="multipart/form-data" action="{{ $payment->exists ? route('payments.update', $payment) : route('payments.store') }}">
        @csrf
        @if($payment->exists)
            @method('PUT')
        @endif

        <div class="grid gap-4 md:grid-cols-2">
            <label class="grid gap-2 text-sm font-bold text-slate-700">Afiliado
                @if($affiliate)
                    <input class="form-input bg-slate-100" value="{{ $affiliate->full_name }} - {{ $affiliate->registration_number }}" disabled>
                    <input type="hidden" name="affiliate_id" value="{{ $affiliate->id }}">
                @else
                    <select class="form-input" name="affiliate_id" required>
                        <option value="">Seleccione afiliado</option>
                        @foreach($affiliates as $candidate)
                            <option value="{{ $candidate->id }}" @selected(old('affiliate_id') == $candidate->id)>{{ $candidate->full_name }} - {{ $candidate->registration_number }}</option>
                        @endforeach
                    </select>
                @endif
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Monto
                <input class="form-input" name="amount" value="{{ old('amount', $payment->paid_amount ?? $payment->amount) }}" inputmode="decimal" required>
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Moneda
                <select class="form-input" name="currency"><option value="BOB" @selected(old('currency', $payment->currency ?? 'BOB') === 'BOB')>BOB</option></select>
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Fecha y hora de pago
                <input class="form-input" type="datetime-local" name="paid_at" value="{{ old('paid_at', optional($payment->paid_at ?? now())->format('Y-m-d\\TH:i')) }}" required>
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Metodo
                <select class="form-input" name="payment_method" required>
                    @foreach(['efectivo' => 'Efectivo', 'qr' => 'QR', 'transferencia' => 'Transferencia', 'deposito' => 'Deposito', 'pos' => 'POS', 'otro' => 'Otro'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', $payment->payment_method) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Estado inicial
                <select class="form-input" name="status" required>
                    <option value="pending" @selected(old('status', $payment->status) === 'pending')>Pendiente</option>
                    <option value="under_review" @selected(old('status', $payment->status) === 'under_review')>En revision</option>
                </select>
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Banco
                <input class="form-input" name="bank_name" value="{{ old('bank_name', $payment->bank_name) }}" maxlength="120">
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Referencia
                <input class="form-input" name="reference_number" value="{{ old('reference_number', $payment->reference_number) }}" maxlength="120">
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Numero de transaccion
                <input class="form-input" name="transaction_number" value="{{ old('transaction_number', $payment->transaction_number) }}" maxlength="120">
            </label>
            <label class="grid gap-2 text-sm font-bold text-slate-700">Comprobante
                <input class="form-input" type="file" name="voucher" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
            </label>
        </div>

        <label class="grid gap-2 text-sm font-bold text-slate-700">Observaciones
            <textarea class="form-input min-h-28" name="observations" maxlength="500">{{ old('observations', $payment->observations) }}</textarea>
        </label>

        <label class="flex items-start gap-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
            <input class="mt-1" type="checkbox" name="duplicate_confirmed" value="1" @checked(old('duplicate_confirmed'))>
            <span>Confirmo que este pago es legitimo aunque el sistema detecte datos similares a otro registro.</span>
        </label>

        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <a class="btn-secondary" href="{{ $payment->exists ? route('payments.show', $payment) : route('payments.index') }}">Cancelar</a>
            <button class="btn-primary">{{ $payment->exists ? 'Guardar cambios' : 'Registrar pago' }}</button>
        </div>
    </form>
</x-layouts.app>
