<x-layouts.app title="Afiliados">
    <form class="mb-4 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-[1fr_220px_auto_auto]" method="get">
        <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre, CI o registro">
        <select class="form-input" name="status">
            <option value="">Todos los estados</option>
            @foreach(['pendiente_pago','activo','inactivo','observado'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Support\AffiliationStatusPresenter::label($status) }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filtrar</button>
        @if(auth()->user()->isInternal() && auth()->user()->hasRole(['superadministrador','administrador','gerente','caja','cajero']))
            <a class="btn-secondary" href="{{ route('affiliates.office.create') }}">+ Afiliacion en oficina</a>
        @endif
        @if(auth()->user()->hasRole(['superadministrador','administrador','administrador_sector','secretaria']))
            <a class="btn-primary" href="{{ route('affiliates.create') }}">Nuevo afiliado</a>
        @endif
    </form>
    <div class="mobile-card-list">
        @foreach($affiliates as $affiliate)
            <article class="mobile-list-card">
                <div class="flex items-start gap-3">
                    <div class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-full bg-siafco-primary-50 text-sm font-black text-siafco-primary-900">
                        @if($affiliate->photo_path)
                            <img class="h-full w-full object-cover" src="{{ Storage::url($affiliate->photo_path) }}" alt="">
                        @else
                            {{ mb_substr($affiliate->full_name, 0, 1) }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="mobile-list-card__title truncate">{{ $affiliate->full_name }}</h2>
                        <p class="mobile-list-card__meta">{{ $affiliate->registration_number }} · {{ $affiliate->sector->name }}</p>
                        <div class="mt-2"><x-affiliation-status :status="\App\Support\AffiliationStatusPresenter::forAffiliate($affiliate)" size="sm" /></div>
                    </div>
                </div>
                <div class="mt-4 grid gap-2">
                    <a class="btn-secondary min-h-12 w-full" href="{{ route('affiliates.show', $affiliate) }}">Abrir</a>
                </div>
            </article>
        @endforeach
    </div>
    <div class="desktop-table overflow-hidden rounded-lg border border-slate-200 bg-white">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Registro</th><th>Nombre</th><th>CI</th><th>Sector</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @foreach($affiliates as $affiliate)
                    <tr>
                        <td class="font-black">{{ $affiliate->registration_number }}</td>
                        <td>{{ $affiliate->full_name }}</td>
                        <td>{{ $affiliate->ci }}</td>
                        <td>{{ $affiliate->sector->name }}</td>
                        <td><x-affiliation-status :status="\App\Support\AffiliationStatusPresenter::forAffiliate($affiliate)" size="sm" /></td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                <a class="btn-secondary px-3 py-2 text-xs" href="{{ route('affiliates.show', $affiliate) }}">Ver</a>
                                @can('delete', $affiliate)
                                    <form method="post" action="{{ route('affiliates.destroy', $affiliate) }}"
                                        data-confirm-title="Eliminar registro"
                                        data-confirm-message="Este registro será eliminado definitivamente junto con sus datos relacionados permitidos. Esta acción no se puede deshacer."
                                        data-confirm-detail="Nombre: {{ $affiliate->full_name }}&#10;CI: {{ $affiliate->ci }}&#10;Estado actual: {{ \App\Support\AffiliationStatusPresenter::label(\App\Support\AffiliationStatusPresenter::forAffiliate($affiliate)) }}"
                                        data-confirm-accept="Eliminar definitivamente"
                                        data-confirm-variant="danger">
                                        @csrf
                                        @method('delete')
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center gap-1.5 rounded border border-red-300 bg-red-50 px-3 py-2 text-xs font-black text-red-800 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                                        title="Eliminar afiliado"
                                        aria-label="Eliminar a {{ $affiliate->full_name }}"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14M10 10v6m4-6v6"></path>
                                        </svg>
                                        <span class="hidden sm:inline">Eliminar</span>
                                    </button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $affiliates->links() }}</div>

</x-layouts.app>
