<x-layouts.app title="Soporte y captación">
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-xs font-black uppercase text-[#b8942f]">Reportes de afiliación</p>
            <h1 class="text-2xl font-black text-[#0b1f3a]">Soporte y captación</h1>
            <p class="mt-1 text-sm text-slate-600">Atribución operativa para seguimiento comercial. No modifica ni reemplaza la auditoría técnica real.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('reports.index') }}">Volver</a>
            @if($canExport)
                <a class="btn-primary" href="{{ route('reports.affiliate-support.csv', request()->query()) }}">Exportar CSV</a>
            @endif
        </div>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <article class="metric-card"><p>Total afiliados</p><strong>{{ $summaryCards['total'] }}</strong></article>
        <article class="metric-card"><p>Web / Express</p><strong>{{ $summaryCards['web'] }}</strong></article>
        <article class="metric-card"><p>Oficina / Internos</p><strong>{{ $summaryCards['office'] }}</strong></article>
        <article class="metric-card"><p>Responsables activos</p><strong>{{ $summaryCards['active_responsibles'] }}</strong></article>
        <article class="metric-card"><p>Aprobados</p><strong>{{ $summaryCards['approved'] }}</strong></article>
        <article class="metric-card"><p>En revisión</p><strong>{{ $summaryCards['review'] }}</strong></article>
    </section>

    <form class="mt-5 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-3 xl:grid-cols-5">
        <input class="form-input" type="date" name="from" value="{{ $filters['from'] ?? '' }}" aria-label="Desde">
        <input class="form-input" type="date" name="to" value="{{ $filters['to'] ?? '' }}" aria-label="Hasta">
        <select class="form-input" name="responsible_user_id">
            <option value="">Todos los responsables</option>
            @foreach($responsibles as $user)
                <option value="{{ $user->id }}" @selected((string) ($filters['responsible_user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <select class="form-input" name="role">
            <option value="">Todos los roles</option>
            @foreach($roles as $role => $label)
                <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="form-input" name="channel">
            <option value="">Todos los canales</option>
            @foreach($channels as $channel => $label)
                <option value="{{ $channel }}" @selected(($filters['channel'] ?? '') === $channel)>{{ $label }}</option>
            @endforeach
        </select>
        <select class="form-input" name="sector_id">
            <option value="">Todos los sectores</option>
            @foreach($sectors as $sector)
                <option value="{{ $sector->id }}" @selected((string) ($filters['sector_id'] ?? '') === (string) $sector->id)>{{ $sector->name }}</option>
            @endforeach
        </select>
        <select class="form-input" name="affiliation_plan_id">
            <option value="">Todos los planes</option>
            @foreach($plans as $plan)
                <option value="{{ $plan->id }}" @selected((string) ($filters['affiliation_plan_id'] ?? '') === (string) $plan->id)>{{ $plan->name }}</option>
            @endforeach
        </select>
        <select class="form-input" name="status">
            <option value="">Todos los estados</option>
            @foreach($affiliationStatuses as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\AffiliationStatusPresenter::label($status) }}</option>
            @endforeach
        </select>
        <select class="form-input" name="payment_status">
            <option value="">Todos los pagos</option>
            @foreach($paymentStatuses as $status)
                <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ \App\Support\PaymentStatus::label($status) }}</option>
            @endforeach
        </select>
        <input class="form-input" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Nombre, CI o registro">
        <button class="btn-secondary">Filtrar</button>
    </form>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-black text-[#0b1f3a]">Resumen por responsable</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Responsable</th><th>Rol</th><th>Web asignados</th><th>Oficina / internos</th><th>Total</th><th>Aprobados</th><th>En revisión</th><th>Otros estados</th></tr></thead>
                <tbody>
                @forelse($responsibleRows as $row)
                    <tr>
                        <td class="font-bold">{{ $row['responsible'] }}</td>
                        <td>{{ $row['role'] }}</td>
                        <td>{{ $row['web'] }}</td>
                        <td>{{ $row['office'] }}</td>
                        <td class="font-black">{{ $row['total'] }}</td>
                        <td>{{ $row['approved'] }}</td>
                        <td>{{ $row['review'] }}</td>
                        <td>{{ $row['other'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8">Sin datos para los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="text-lg font-black text-[#0b1f3a]">Detalle de afiliados</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="table">
                <thead><tr><th>Registro</th><th>Nombre</th><th>CI</th><th>Sector</th><th>Plan</th><th>Canal</th><th>Responsable soporte</th><th>Rol</th><th>Fecha de captación</th><th>Estado afiliación</th><th>Estado pago</th></tr></thead>
                <tbody>
                @forelse($affiliates as $affiliate)
                    @php
                        $resolved = $attribution->resolve($affiliate);
                        $responsible = $resolved['responsible_user'];
                    @endphp
                    <tr>
                        <td class="font-black">{{ $affiliate->registration_number ?: 'Pendiente' }}</td>
                        <td>{{ $affiliate->full_name }}</td>
                        <td>{{ $affiliate->ci }}</td>
                        <td>{{ $affiliate->sector?->name ?? 'Sin sector' }}</td>
                        <td>{{ $affiliate->plan?->name ?? 'Sin plan' }}</td>
                        <td><span class="badge {{ \App\Support\AffiliateSupportChannel::badgeClasses($resolved['channel']) }}">{{ \App\Support\AffiliateSupportChannel::label($resolved['channel']) }}</span></td>
                        <td>{{ $responsible?->name ?? ($resolved['attribution_type'] === 'web_assigned' ? 'Sin responsable configurado' : 'Sin responsable') }}</td>
                        <td>{{ $responsible?->roleLabel() ?? 'No aplica' }}</td>
                        <td>{{ $affiliate->publicRequest ? \App\Support\SiafcoDate::dateTime($affiliate->publicRequest->submitted_at) : \App\Support\SiafcoDate::dateTime($affiliate->created_at) }}</td>
                        <td><x-affiliation-status :status="$affiliate->status" size="sm" /></td>
                        <td><x-payment-status :status="$affiliate->latestPayment?->status" size="sm" /></td>
                    </tr>
                @empty
                    <tr><td colspan="11">Sin afiliados para los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $affiliates->links() }}</div>
    </section>
</x-layouts.app>
