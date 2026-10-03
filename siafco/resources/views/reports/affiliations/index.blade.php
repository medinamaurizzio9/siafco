<x-layouts.app title="Reporte de Afiliaciones">
    <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-wide text-[#b8942f]">Reportes</p>
            <h1 class="text-2xl font-black text-[#0b1f3a]">Reporte de Afiliaciones</h1>
            <p class="mt-1 text-sm text-slate-600">Consulta, filtra y exporta la información de afiliaciones.</p>
        </div>
        @if($canExport)
            <a class="btn-primary" href="{{ route('reports.affiliations.export', request()->query()) }}">Exportar Excel</a>
        @endif
    </div>

    <form class="section-card mb-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        <div>
            <label class="form-label" for="date_from">Fecha desde</label>
            <input id="date_from" class="form-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">
        </div>
        <div>
            <label class="form-label" for="date_to">Fecha hasta</label>
            <input id="date_to" class="form-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">
        </div>
        <div>
            <label class="form-label" for="code">Código de afiliado</label>
            <input id="code" class="form-input" name="code" value="{{ $filters['code'] ?? '' }}">
        </div>
        <div>
            <label class="form-label" for="name">Nombre del afiliado</label>
            <input id="name" class="form-input" name="name" value="{{ $filters['name'] ?? '' }}">
        </div>
        <div>
            <label class="form-label" for="ci">CI</label>
            <input id="ci" class="form-input" name="ci" value="{{ $filters['ci'] ?? '' }}">
        </div>
        <div>
            <label class="form-label" for="organization">Organización</label>
            <input id="organization" class="form-input" name="organization" value="{{ $filters['organization'] ?? '' }}">
        </div>
        <div>
            <label class="form-label" for="sector_id">Sindicato</label>
            <select id="sector_id" class="form-input" name="sector_id">
                <option value="">Todos</option>
                @foreach($sectors as $sector)
                    <option value="{{ $sector->id }}" @selected((string) ($filters['sector_id'] ?? '') === (string) $sector->id)>{{ $sector->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="origin">Origen</label>
            <select id="origin" class="form-input" name="origin">
                <option value="">Todos</option>
                @foreach($origins as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['origin'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="registered_by">Registrado por</label>
            <select id="registered_by" class="form-input" name="registered_by">
                <option value="">Todos</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['registered_by'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="managed_by">Gestionado por</label>
            <select id="managed_by" class="form-input" name="managed_by">
                <option value="">Todos</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['managed_by'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="approved_by">Aprobado por</label>
            <select id="approved_by" class="form-input" name="approved_by">
                <option value="">Todos</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}" @selected((string) ($filters['approved_by'] ?? '') === (string) $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="status">Estado</label>
            <select id="status" class="form-input" name="status">
                <option value="">Todos</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ \App\Support\AffiliationStatusPresenter::label($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-wrap items-end gap-3 md:col-span-2 xl:col-span-4">
            <button class="btn-secondary">Aplicar filtros</button>
            <a class="btn-secondary" href="{{ route('reports.affiliations.index') }}">Limpiar</a>
            @if($canExport)
                <a class="btn-primary" href="{{ route('reports.affiliations.export', request()->query()) }}">Exportar Excel</a>
            @endif
        </div>
    </form>

    <section class="section-card">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="font-black text-[#0b1f3a]">Tabla</h2>
                <p class="text-sm text-slate-600">{{ $affiliates->total() }} registros encontrados</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="table min-w-[1200px]">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Código</th>
                        <th>Afiliado</th>
                        <th>CI</th>
                        <th>Organización</th>
                        <th>Sindicato</th>
                        <th>Origen</th>
                        <th>Registrado por</th>
                        <th>Gestionado por</th>
                        <th>Aprobado por</th>
                        <th>Fecha aprobación</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($affiliates as $affiliate)
                    @php($row = $report->row($affiliate))
                    <tr>
                        <td>{{ \App\Support\SiafcoDate::dateTime($row['affiliation_date'], 'Sin fecha') }}</td>
                        <td class="font-black">{{ $row['code'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['ci'] }}</td>
                        <td>{{ $row['organization'] }}</td>
                        <td>{{ $row['sector'] }}</td>
                        <td>{{ $row['origin'] }}</td>
                        <td>{{ $row['registered_by'] }}</td>
                        <td>{{ $row['managed_by'] }}</td>
                        <td>{{ $row['approved_by'] }}</td>
                        <td>{{ \App\Support\SiafcoDate::dateTime($row['approved_at'], 'No disponible') }}</td>
                        <td><x-affiliation-status :status="$row['status']" /></td>
                    </tr>
                @empty
                    <tr><td colspan="12">Sin afiliaciones para los filtros seleccionados.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5">
            {{ $affiliates->links() }}
        </div>
    </section>
</x-layouts.app>
