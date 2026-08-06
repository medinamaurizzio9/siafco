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
        @if(auth()->user()->hasRole(['administrador','administrador_sector','secretaria']))
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
                        <div class="mt-2"><x-affiliation-status :status="$affiliate->status" size="sm" /></div>
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
                        <td><x-affiliation-status :status="$affiliate->status" size="sm" /></td>
                        <td>
                            <div class="flex items-center justify-end gap-2">
                                <a class="btn-secondary px-3 py-2 text-xs" href="{{ route('affiliates.show', $affiliate) }}">Ver</a>
                                @can('delete', $affiliate)
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center gap-1.5 rounded border border-red-300 bg-red-50 px-3 py-2 text-xs font-black text-red-800 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2"
                                        title="Eliminar afiliado"
                                        aria-label="Eliminar a {{ $affiliate->full_name }}"
                                        data-delete-affiliate-trigger
                                        data-delete-url="{{ route('affiliates.destroy', $affiliate) }}"
                                        data-affiliate-name="{{ $affiliate->full_name }}"
                                        data-affiliate-number="{{ $affiliate->registration_number }}"
                                        data-affiliate-ci="{{ $affiliate->ci }}"
                                    >
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="M3 6h18M8 6V4h8v2m-9 0 1 14h8l1-14M10 10v6m4-6v6"></path>
                                        </svg>
                                        <span class="hidden sm:inline">Eliminar</span>
                                    </button>
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

    <div class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/65 p-4" data-delete-affiliate-modal role="dialog" aria-modal="true" aria-labelledby="delete-affiliate-title">
        <div class="w-full max-w-lg rounded-lg bg-white p-6 shadow-2xl">
            <div class="flex items-start gap-4">
                <div class="grid h-11 w-11 flex-none place-items-center rounded-full bg-red-100 text-red-800" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 9v4m0 4h.01M10.3 3.7 2.4 17.4A2 2 0 0 0 4.1 20h15.8a2 2 0 0 0 1.7-2.6L13.7 3.7a2 2 0 0 0-3.4 0Z"></path>
                    </svg>
                </div>
                <div>
                    <h2 id="delete-affiliate-title" class="text-xl font-black text-red-900">ELIMINAR AFILIADO</h2>
                    <p class="mt-2 text-sm text-slate-700">Está a punto de eliminar permanentemente del acceso operativo a:</p>
                </div>
            </div>

            <dl class="mt-5 grid gap-2 rounded border border-slate-200 bg-slate-50 p-4 text-sm">
                <div><dt class="text-xs font-black uppercase text-slate-500">Nombre completo</dt><dd class="font-bold text-slate-950" data-delete-name></dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Número de afiliado</dt><dd data-delete-number></dd></div>
                <div><dt class="text-xs font-black uppercase text-slate-500">Cédula</dt><dd data-delete-ci></dd></div>
            </dl>

            <p class="mt-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                Esta acción puede afectar credenciales, pagos, documentos e historial relacionado. Los registros históricos y archivos serán conservados.
            </p>

            <form method="post" class="mt-5" data-delete-affiliate-form>
                @csrf
                @method('delete')
                <label class="form-label" for="deletion_reason">Motivo de eliminación <span class="text-red-700">*</span></label>
                <textarea id="deletion_reason" class="form-input" name="deletion_reason" rows="3" maxlength="500" required data-delete-reason></textarea>

                <label class="form-label mt-4" for="delete_confirmation">Para confirmar, escriba exactamente <strong>ELIMINAR</strong></label>
                <input id="delete_confirmation" class="form-input" name="confirmation" autocomplete="off" required data-delete-confirmation>

                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="btn-secondary" data-delete-cancel>Cancelar</button>
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded bg-red-700 px-4 py-2 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-45" disabled data-delete-submit>
                        Eliminar definitivamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
