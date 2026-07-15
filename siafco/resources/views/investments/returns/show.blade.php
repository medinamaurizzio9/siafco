<x-layouts.app title="Rendimiento {{ $period->due_date->format('d/m/Y') }}">
    <div class="grid gap-5 lg:grid-cols-2">
        <div class="section-card">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div><dt class="font-bold text-slate-500">Accionista</dt><dd>{{ $period->lot->investor->person->full_name }}</dd></div>
                <div><dt class="font-bold text-slate-500">Lote</dt><dd>{{ $period->lot->purchase_number }}</dd></div>
                <div><dt class="font-bold text-slate-500">Base</dt><dd>Bs {{ number_format($period->base_return_amount, 2) }}</dd></div>
                <div><dt class="font-bold text-slate-500">Total</dt><dd>Bs {{ number_format($period->total_amount, 2) }}</dd></div>
                <div><dt class="font-bold text-slate-500">Estado</dt><dd><span class="badge">{{ $period->status }}</span></dd></div>
            </dl>
        </div>
        <form class="section-card grid gap-3" method="post" action="{{ route('investments.returns.prepare', $period) }}">
            @csrf
            <h2 class="text-lg font-black text-[#0b1f3a]">Preparar rendimiento</h2>
            <input class="form-input" type="number" step="0.01" name="production_bonus_amount" value="{{ old('production_bonus_amount', $period->production_bonus_amount) }}" placeholder="Bono por produccion minera">
            <input class="form-input" name="extra_concept" value="{{ old('extra_concept', $period->extra_concept) }}" placeholder="Concepto extra">
            <input class="form-input" type="number" step="0.01" name="extra_amount" value="{{ old('extra_amount', $period->extra_amount) }}" placeholder="Monto extra">
            <input class="form-input" type="number" step="0.01" name="deductions_amount" value="{{ old('deductions_amount', $period->deductions_amount) }}" placeholder="Deducciones">
            <textarea class="form-input" name="notes" rows="2" placeholder="Notas">{{ old('notes', $period->notes) }}</textarea>
            <button class="btn-primary">Solicitar aprobacion</button>
        </form>
    </div>
    <div class="mt-5 flex flex-wrap gap-3">
        @if($period->status === 'pending_approval')
            <form method="post" action="{{ route('investments.returns.approve', $period) }}">@csrf <button class="btn-primary">Aprobar</button></form>
            <form class="flex gap-2" method="post" action="{{ route('investments.returns.reject', $period) }}">@csrf <input class="form-input" name="notes" placeholder="Motivo rechazo" required><button class="btn-danger">Rechazar</button></form>
        @endif
        @if($period->status === 'approved')
            <form class="flex flex-wrap gap-2" method="post" action="{{ route('investments.receipts.issue', $period) }}">
                @csrf
                <input class="form-input max-w-xs" name="payment_method" placeholder="Metodo de pago" required>
                <input class="form-input max-w-xs" name="payment_reference" placeholder="Transaccion">
                <button class="btn-primary">Registrar pago y emitir recibo</button>
            </form>
        @endif
        @if($period->receipt)
            <a class="btn-secondary" href="{{ route('investments.receipts.show', $period->receipt) }}">Ver recibo</a>
        @endif
    </div>
</x-layouts.app>
