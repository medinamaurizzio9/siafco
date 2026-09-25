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
        <article class="metric-card"><p>Credenciales generadas</p><strong>{{ $credentials }}</strong></article>
        <article class="metric-card"><p>Ingresos</p><strong>Bs {{ number_format($income, 2) }}</strong></article>
    </div>
    @if(auth()->user()->hasPermission('affiliate_support_reports.view'))
        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase text-[#b8942f]">Afiliación</p>
                    <h2 class="text-xl font-black text-[#0b1f3a]">Soporte y captación</h2>
                    <p class="mt-1 text-sm text-slate-600">Atribución operativa por responsable comercial sin alterar auditoría histórica.</p>
                </div>
                <a class="btn-primary" href="{{ route('reports.affiliate-support.index') }}">Abrir reporte</a>
            </div>
        </section>
    @endif
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

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-xs font-black uppercase text-[#b8942f]">Entrega de joyas</p>
                <h2 class="text-xl font-black text-[#0b1f3a]">Control de entregados y pendientes</h2>
            </div>
            @if(auth()->user()->hasPermission('affiliate_jewels.report') || auth()->user()->hasPermission('reports.export'))
                <a class="btn-primary" href="{{ route('reports.jewels.csv', request()->query()) }}">Exportar CSV</a>
            @endif
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <article class="metric-card"><p>Total afiliados</p><strong>{{ $jewelTotal }}</strong></article>
            <article class="metric-card"><p>Joyas entregadas</p><strong>{{ $jewelDelivered }}</strong></article>
            <article class="metric-card"><p>Pendientes</p><strong>{{ $jewelPending }}</strong></article>
            <article class="metric-card"><p>% entregado</p><strong>{{ number_format($jewelPercent, 1) }}%</strong></article>
        </div>

        <form class="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 md:grid-cols-3 xl:grid-cols-7">
            <input type="hidden" name="from" value="{{ optional($from)->format('Y-m-d') }}">
            <input type="hidden" name="to" value="{{ optional($to)->format('Y-m-d') }}">
            <select class="form-input" name="jewel_status">
                <option value="">Todos</option>
                <option value="delivered" @selected(request('jewel_status') === 'delivered')>Entregada</option>
                <option value="pending" @selected(request('jewel_status') === 'pending')>Pendiente</option>
            </select>
            <input class="form-input" type="date" name="jewel_from" value="{{ request('jewel_from') }}" aria-label="Fecha desde">
            <input class="form-input" type="date" name="jewel_to" value="{{ request('jewel_to') }}" aria-label="Fecha hasta">
            <select class="form-input" name="jewel_sector_id">
                <option value="">Todos los sectores</option>
                @foreach($jewelSectors as $sector)
                    <option value="{{ $sector->id }}" @selected((string) request('jewel_sector_id') === (string) $sector->id)>{{ $sector->name }}</option>
                @endforeach
            </select>
            <select class="form-input" name="jewel_delivered_by">
                <option value="">Todos los usuarios</option>
                @foreach($jewelUsers as $user)
                    <option value="{{ $user->id }}" @selected((string) request('jewel_delivered_by') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
            <input class="form-input" name="jewel_search" value="{{ request('jewel_search') }}" placeholder="Nombre, CI o código">
            <button class="btn-secondary">Filtrar</button>
        </form>

        <div class="mt-5 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Código afiliado</th><th>Nombre</th><th>CI</th><th>Sector</th><th>Estado de joya</th><th>Fecha de entrega</th><th>Entregada por</th></tr></thead>
                <tbody>
                @forelse($jewelRows as $affiliate)
                    <tr>
                        <td class="font-black">{{ $affiliate->registration_number ?: 'Pendiente' }}</td>
                        <td>{{ $affiliate->full_name }}</td>
                        <td>{{ $affiliate->ci }}</td>
                        <td>{{ $affiliate->sector?->name ?? 'Sin sector' }}</td>
                        <td><span class="badge {{ $affiliate->jewel_delivered_at ? '!bg-emerald-100 !text-emerald-800' : '!bg-amber-100 !text-amber-900' }}">{{ $affiliate->jewel_delivered_at ? 'ENTREGADA' : 'PENDIENTE' }}</span></td>
                        <td>{{ \App\Support\SiafcoDate::dateTime($affiliate->jewel_delivered_at, 'Sin entrega') }}</td>
                        <td>{{ $affiliate->jewelDeliveredBy?->name ?? 'No registrado' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">Sin afiliados para los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layouts.app>
