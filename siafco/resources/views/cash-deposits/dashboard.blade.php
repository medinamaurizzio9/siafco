<x-layouts.app title="Panel Caja">
    @php
        $cashMetrics = [
            ['Efectivo confirmado', $summary['confirmed_cash'], 'Pagos confirmados', 'credit-card', 'border-emerald-200 bg-emerald-50 text-emerald-800'],
            ['Pendiente de depositar', $summary['pending_total'], 'Disponible en caja', 'receipt', 'border-amber-200 bg-amber-50 text-amber-800'],
            ['Depósitos en revisión', $summary['review_deposits'], 'En validación', 'search', 'border-sky-200 bg-sky-50 text-sky-800'],
            ['Depósitos confirmados', $summary['confirmed_deposits'], 'Rendiciones verificadas', 'check', 'border-green-200 bg-green-50 text-green-800'],
            ['Cobros en efectivo en revisión', $summary['cash_under_review'], 'Pendientes de confirmar', 'credit-card', 'border-indigo-200 bg-indigo-50 text-indigo-800'],
        ];
    @endphp

    <div class="space-y-5">
        <header class="relative overflow-hidden rounded-2xl border border-blue-100 bg-gradient-to-r from-white via-blue-50 to-sky-100 px-5 py-4 shadow-sm">
            <div class="pointer-events-none absolute left-1/2 top-1/2 hidden -translate-y-1/2 text-[#0b3b70]/5 lg:block"><x-ui.icon name="credit-card" class="h-28 w-28" /></div>
            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase text-[#b28a24]">Hola,</p>
                    <p class="text-xl font-black uppercase text-[#0b1f3a] sm:text-2xl">{{ auth()->user()->name }}</p>
                    <h2 class="mt-0.5 text-2xl font-black text-[#0b1f3a]">Panel Caja</h2>
                    <p class="mt-1 max-w-2xl text-sm text-slate-600">Gestiona afiliaciones, cobros y rendiciones de efectivo de forma rápida y segura.</p>
                </div>
                <div class="flex shrink-0 items-center gap-3 rounded-xl border border-blue-100 bg-white/80 px-4 py-3 text-sm font-semibold text-[#0b1f3a] shadow-sm">
                    <x-ui.icon name="calendar" class="h-5 w-5 text-[#0b3b70]" />
                    <span>{{ now()->translatedFormat('l, j \d\e F \d\e Y') }}</span>
                </div>
            </div>
        </header>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" aria-label="Acciones de caja">
            <article class="group relative flex h-full min-h-48 flex-col overflow-hidden rounded-xl bg-gradient-to-br from-blue-600 to-[#063c78] p-4 text-white shadow-md transition duration-200 hover:-translate-y-0.5 hover:shadow-lg" data-panel-action="office-affiliation">
                <span class="pointer-events-none absolute -bottom-5 -right-4 text-white/10"><x-ui.icon name="user" class="h-28 w-28" /></span>
                <span class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 text-white ring-1 ring-white/20"><x-ui.icon name="user-plus" class="h-5 w-5" /></span>
                <h3 class="relative mt-3 text-lg font-black">Nueva afiliación en oficina</h3>
                <p class="relative mt-1 flex-1 text-sm leading-5 text-white/85">Registra una nueva persona y su pago presencial.</p>
                <a class="relative mt-auto inline-flex w-full items-center justify-between rounded-lg bg-white px-4 py-2.5 text-sm font-black text-[#0b1f3a] shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-white" href="{{ route('affiliates.office.create') }}"><span>Registrar afiliación</span><x-ui.icon name="arrow-right" class="h-4 w-4" /></a>
            </article>
            <article class="group relative flex h-full min-h-48 flex-col overflow-hidden rounded-xl bg-gradient-to-br from-emerald-600 to-emerald-800 p-4 text-white shadow-md transition duration-200 hover:-translate-y-0.5 hover:shadow-lg" data-panel-action="pending-affiliation">
                <span class="pointer-events-none absolute -bottom-5 -right-4 text-white/10"><x-ui.icon name="users" class="h-28 w-28" /></span>
                <span class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 text-white ring-1 ring-white/20"><x-ui.icon name="search" class="h-5 w-5" /></span>
                <h3 class="relative mt-3 text-lg font-black">Cobrar afiliación pendiente</h3>
                <p class="relative mt-1 flex-1 text-sm leading-5 text-white/85">Busca a una persona que ya realizó su registro y desea pagar en oficina.</p>
                <a class="relative mt-auto inline-flex w-full items-center justify-between rounded-lg bg-white px-4 py-2.5 text-sm font-black text-[#0b1f3a] shadow-sm transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-white" href="{{ route('public-affiliation.admin.secretary-payments') }}"><span>Buscar persona</span><x-ui.icon name="arrow-right" class="h-4 w-4" /></a>
            </article>
            <article class="group relative flex h-full min-h-48 flex-col overflow-hidden rounded-xl bg-gradient-to-br from-[#d1a719] to-[#9a7000] p-4 text-white shadow-md transition duration-200 hover:-translate-y-0.5 hover:shadow-lg" data-panel-action="cash-deposit">
                <span class="pointer-events-none absolute -bottom-5 -right-4 text-white/10"><x-ui.icon name="landmark" class="h-28 w-28" /></span>
                <div class="flex items-start justify-between gap-3">
                    <span class="relative flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 text-white ring-1 ring-white/20"><x-ui.icon name="landmark" class="h-5 w-5" /></span>
                    <div class="relative text-right"><span class="block text-xs font-bold uppercase text-white/80">Disponible para depositar</span><strong class="text-lg text-white">BOB {{ number_format($summary['available'], 2) }}</strong></div>
                </div>
                <h3 class="relative mt-3 text-lg font-black">Registrar depósito de caja</h3>
                <p class="relative mt-1 flex-1 text-sm leading-5 text-white/85">Registra el depósito o entrega del efectivo recaudado.</p>
                <button class="relative mt-auto inline-flex w-full items-center justify-between rounded-lg bg-white px-4 py-2.5 text-sm font-black text-[#0b1f3a] shadow-sm transition hover:bg-amber-50 focus:outline-none focus:ring-2 focus:ring-white disabled:cursor-not-allowed disabled:bg-white/60 disabled:text-slate-500 disabled:shadow-none" type="button" onclick="document.getElementById('cash-deposit-dialog').showModal()" @disabled($summary['available'] <= 0)><span>Registrar depósito</span><x-ui.icon name="arrow-right" class="h-4 w-4" /></button>
            </article>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="cash-summary-title">
            <h3 id="cash-summary-title" class="mb-3 flex items-center gap-2 text-lg font-black text-[#0b1f3a]"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-[#0b3b70]"><x-ui.icon name="credit-card" class="h-4 w-4" /></span>Resumen de caja</h3>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                @foreach($cashMetrics as [$label, $amount, $description, $icon, $classes])
                    <article class="rounded-xl border p-3 {{ $classes }}" data-cash-metric>
                        <div class="flex items-start gap-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white/70"><x-ui.icon :name="$icon" class="h-4 w-4" /></span>
                            <div class="min-w-0"><p class="text-[11px] font-black uppercase leading-4">{{ $label }}</p><strong class="mt-1 block text-lg leading-6 text-[#0b1f3a]">BOB {{ number_format($amount, 2) }}</strong></div>
                        </div>
                        <p class="mt-1 text-[11px] leading-4 opacity-80">{{ $description }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="recent-payments-title">
                <header class="mb-3 flex items-center justify-between gap-3">
                    <h3 id="recent-payments-title" class="flex items-center gap-2 text-lg font-black text-[#0b1f3a]"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-[#0b3b70]"><x-ui.icon name="credit-card" class="h-4 w-4" /></span>Mis últimos cobros</h3>
                    <a class="inline-flex items-center gap-1 text-xs font-bold text-[#0b3b70] hover:text-[#b28a24] hover:underline" href="{{ route('admin.collections.index') }}">Ver todos<x-ui.icon name="arrow-right" class="h-3.5 w-3.5" /></a>
                </header>
                <div class="overflow-x-auto xl:overflow-visible">
                    <table class="w-full min-w-[620px] table-fixed text-left text-xs xl:min-w-0" data-cash-table="payments">
                        <thead class="bg-[#0b1f3a] text-[10px] font-black uppercase text-[#f0c633]"><tr><th class="w-[15%] px-2 py-2">Fecha</th><th class="w-[24%] px-2 py-2">Nombre</th><th class="w-[14%] px-2 py-2">CI</th><th class="w-[16%] px-2 py-2">Método</th><th class="w-[16%] px-2 py-2">Monto</th><th class="w-[15%] px-2 py-2">Estado</th></tr></thead>
                        <tbody>
                            @forelse($recentPayments->take(5) as $payment)
                                <tr class="border-t border-slate-100 hover:bg-slate-50"><td class="px-2 py-2 align-top">{{ ($payment->paid_at ?? $payment->created_at)->format('d/m/Y') }}</td><td class="break-words px-2 py-2 align-top font-semibold text-[#0b1f3a]">{{ $payment->affiliate?->full_name ?? 'No disponible' }}</td><td class="break-words px-2 py-2 align-top">{{ $payment->affiliate?->ci ?? '—' }}</td><td class="break-words px-2 py-2 align-top">{{ \App\Support\PaymentMethodPresenter::label($payment->payment_method) }}</td><td class="px-2 py-2 align-top font-bold whitespace-nowrap">{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</td><td class="px-2 py-2 align-top"><x-payment-status :status="$payment->status" size="sm" /></td></tr>
                            @empty
                                <tr><td class="px-2 py-4 text-slate-500" colspan="6">Sin cobros registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="recent-deposits-title">
                <header class="mb-3"><h3 id="recent-deposits-title" class="flex items-center gap-2 text-lg font-black text-[#0b1f3a]"><span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"><x-ui.icon name="landmark" class="h-4 w-4" /></span>Mis depósitos recientes</h3></header>
                <div class="overflow-x-auto xl:overflow-visible">
                    <table class="w-full min-w-[620px] table-fixed text-left text-xs xl:min-w-0" data-cash-table="deposits">
                        <thead class="bg-[#0b1f3a] text-[10px] font-black uppercase text-[#f0c633]"><tr><th class="w-[15%] px-2 py-2">Fecha</th><th class="w-[17%] px-2 py-2">Monto</th><th class="w-[22%] px-2 py-2">N.º transacción</th><th class="w-[17%] px-2 py-2">Estado</th><th class="w-[18%] px-2 py-2">Revisado por</th><th class="w-[11%] px-2 py-2"><span class="sr-only">Acción</span></th></tr></thead>
                        <tbody>
                            @forelse($recentDeposits->take(5) as $deposit)
                                <tr class="border-t border-slate-100 hover:bg-slate-50"><td class="px-2 py-2 align-top">{{ $deposit->deposited_at->format('d/m/Y') }}</td><td class="px-2 py-2 align-top font-bold whitespace-nowrap">{{ $deposit->currency }} {{ number_format((float) $deposit->amount, 2) }}</td><td class="break-words px-2 py-2 align-top">{{ $deposit->transaction_number }}</td><td class="px-2 py-2 align-top"><span class="font-bold {{ $deposit->status === 'confirmed' ? 'text-green-700' : ($deposit->status === 'rejected' ? 'text-red-700' : 'text-sky-700') }}">{{ $deposit->status === 'confirmed' ? 'Confirmado' : ($deposit->status === 'rejected' ? 'Rechazado' : 'En revisión') }}</span>@if($deposit->rejection_reason)<p class="mt-1 text-[11px] text-red-700">{{ $deposit->rejection_reason }}</p>@endif</td><td class="break-words px-2 py-2 align-top">{{ $deposit->reviewer?->name ?? 'Pendiente' }}</td><td class="px-2 py-2 text-right align-top">@if($deposit->voucher_path)<a class="inline-flex text-[#0b3b70] hover:text-[#b28a24]" target="_blank" href="{{ route('cash-deposits.voucher.own', $deposit) }}" aria-label="Ver comprobante"><x-ui.icon name="eye" class="h-4 w-4" /></a>@else—@endif</td></tr>
                            @empty
                                <tr><td class="px-2 py-4 text-slate-500" colspan="6">Sin depósitos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    <dialog id="cash-deposit-dialog" class="fixed inset-0 m-auto max-h-[calc(100dvh-2rem)] w-[calc(100%-2rem)] max-w-xl overflow-y-auto rounded-xl p-0 shadow-2xl backdrop:bg-black/60">
        <form class="space-y-4 p-6" method="post" action="{{ route('cash-deposits.store') }}" enctype="multipart/form-data">@csrf
            <div class="flex justify-between gap-4"><div><h2 class="text-xl font-black">Registrar depósito</h2><p class="text-sm text-slate-600">Disponible: BOB {{ number_format($summary['available'], 2) }}</p></div><button type="button" aria-label="Cerrar" class="text-2xl" onclick="this.closest('dialog').close()">&times;</button></div>
            @if($errors->any())<div class="rounded-lg border border-red-300 bg-red-50 p-3 text-red-800">{{ $errors->first() }}</div>@endif
            <label><span class="form-label">Monto depositado</span><input class="form-input" name="amount" type="number" min="0.01" max="{{ number_format($summary['available'], 2, '.', '') }}" step="0.01" value="{{ old('amount') }}" required></label>
            <label><span class="form-label">N.º de transacción</span><input class="form-input" name="transaction_number" maxlength="120" value="{{ old('transaction_number') }}" required></label>
            <label><span class="form-label">Fecha del depósito</span><input class="form-input" name="deposited_at" type="datetime-local" value="{{ old('deposited_at', now()->format('Y-m-d\TH:i')) }}" required></label>
            <label><span class="form-label">Comprobante (opcional)</span><input class="form-input" name="voucher" type="file" accept="image/jpeg,image/png,image/webp,application/pdf"></label>
            <label><span class="form-label">Observación (opcional)</span><textarea class="form-input" name="observations" maxlength="500">{{ old('observations') }}</textarea></label>
            <div class="flex justify-end gap-3"><button class="btn-secondary" type="button" onclick="this.closest('dialog').close()">Cancelar</button><button class="btn-primary" type="submit">Registrar depósito</button></div>
        </form>
    </dialog>
    @if($errors->any())<script>document.getElementById('cash-deposit-dialog').showModal();</script>@endif
</x-layouts.app>
