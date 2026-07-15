<div class="rounded border border-slate-200 bg-white p-6 text-slate-900">
    <div class="flex items-start justify-between gap-4 border-b pb-4">
        <div>
            <h2 class="text-2xl font-black text-[#0b1f3a]">{{ $receipt->company_name_snapshot }}</h2>
            <p>NIT: {{ $receipt->company_nit_snapshot ?: 'S/N' }}</p>
            <p>{{ $receipt->company_address_snapshot }}</p>
            <p>{{ $receipt->company_phone_snapshot }} {{ $receipt->company_email_snapshot }}</p>
        </div>
        <div class="text-right">
            <p class="text-sm font-bold text-slate-500">RECIBO</p>
            <p class="text-xl font-black text-[#d4af37]">{{ $receipt->receipt_number }}</p>
            <p>{{ $receipt->issue_date->format('d/m/Y') }}</p>
        </div>
    </div>
    <div class="mt-5 grid gap-3 md:grid-cols-2">
        <p><strong>Accionista:</strong> {{ $receipt->investor_name_snapshot }}</p>
        <p><strong>CI:</strong> {{ $receipt->investor_ci_snapshot }}</p>
        <p><strong>Nro. accionista:</strong> {{ $receipt->investor_number_snapshot }}</p>
        <p><strong>Lote:</strong> {{ $receipt->purchase_number_snapshot }}</p>
        <p><strong>Acciones:</strong> {{ $receipt->shares_quantity_snapshot }}</p>
        <p><strong>Valor unitario:</strong> Bs {{ number_format($receipt->share_unit_price_snapshot, 2) }}</p>
        <p><strong>Capital:</strong> Bs {{ number_format($receipt->invested_capital_snapshot, 2) }}</p>
        <p><strong>Porcentaje mensual:</strong> {{ $receipt->return_percentage_snapshot }}%</p>
    </div>
    <table class="mt-5 w-full text-left">
        <tbody>
        <tr><td class="py-2">Rendimiento mensual</td><td class="py-2 text-right">Bs {{ number_format($receipt->base_return_amount, 2) }}</td></tr>
        <tr><td class="py-2">Bono por produccion minera</td><td class="py-2 text-right">Bs {{ number_format($receipt->production_bonus_amount, 2) }}</td></tr>
        <tr><td class="py-2">{{ $receipt->extra_concept ?: 'Otro extra manual' }}</td><td class="py-2 text-right">Bs {{ number_format($receipt->extra_amount, 2) }}</td></tr>
        <tr><td class="py-2">Deducciones</td><td class="py-2 text-right">Bs {{ number_format($receipt->deductions_amount, 2) }}</td></tr>
        <tr class="border-t text-xl font-black"><td class="py-3">Total pagado</td><td class="py-3 text-right">Bs {{ number_format($receipt->total_amount, 2) }}</td></tr>
        </tbody>
    </table>
    <p class="mt-4"><strong>Metodo:</strong> {{ $receipt->payment_method }} {{ $receipt->payment_reference }}</p>
    <div class="mt-12 grid grid-cols-2 gap-10 text-center">
        <div class="border-t pt-2">Firma caja</div>
        <div class="border-t pt-2">Firma accionista</div>
    </div>
</div>
