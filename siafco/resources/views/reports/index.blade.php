<x-layouts.app title="Reportes basicos">
    <form class="mb-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 sm:grid-cols-[1fr_1fr_auto_auto]">
        <input class="form-input" type="date" name="from" value="{{ optional($from)->format('Y-m-d') }}">
        <input class="form-input" type="date" name="to" value="{{ optional($to)->format('Y-m-d') }}">
        <button class="btn-secondary">Filtrar ingresos</button>
        <a class="btn-primary" href="{{ route('reports.pdf', request()->only('from', 'to')) }}">PDF</a>
    </form>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article class="metric-card"><p>Pagos pendientes</p><strong>{{ $pendingPayments }}</strong></article>
        <article class="metric-card"><p>Pagos confirmados</p><strong>{{ $confirmedPayments }}</strong></article>
        <article class="metric-card"><p>Credenciales</p><strong>{{ $credentials }}</strong></article>
        <article class="metric-card"><p>Ingresos</p><strong>Bs {{ number_format($income, 2) }}</strong></article>
    </div>
    <div class="mt-6 grid gap-5 lg:grid-cols-2">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-black">Afiliados por sector</h2>
            @foreach($bySector as $row)
                <div class="mt-3 flex justify-between border-b border-slate-100 pb-2"><span>{{ $row->sector->name }}</span><strong>{{ $row->total }}</strong></div>
            @endforeach
        </section>
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-black">Activos e inactivos</h2>
            @foreach($byStatus as $status => $total)
                <div class="mt-3 flex justify-between border-b border-slate-100 pb-2"><span>{{ \App\Support\AffiliationStatusPresenter::label($status) }}</span><strong>{{ $total }}</strong></div>
            @endforeach
        </section>
    </div>
</x-layouts.app>
