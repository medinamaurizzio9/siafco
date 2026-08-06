<x-layouts.app :title="$title">
    <div class="grid gap-6">
        <x-ui.card eyebrow="Administracion" title="Auditoria">
            <form method="get" class="grid gap-3 md:grid-cols-4">
                <input class="form-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" aria-label="Fecha desde">
                <input class="form-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" aria-label="Fecha hasta">
                <select class="form-input" name="user_id" aria-label="Actor">
                    <option value="">Actor</option>
                    @foreach($actors as $actor)
                        <option value="{{ $actor->id }}" @selected(($filters['user_id'] ?? '') == $actor->id)>{{ $actor->name }}</option>
                    @endforeach
                </select>
                <select class="form-input" name="role" aria-label="Rol">
                    <option value="">Rol</option>
                    @foreach($roles as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['role'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="form-input" type="text" name="action" value="{{ $filters['action'] ?? '' }}" placeholder="Accion">
                <select class="form-input" name="module" aria-label="Modulo">
                    <option value="">Modulo</option>
                    @foreach($modules as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['module'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input class="form-input" type="text" name="entity" value="{{ $filters['entity'] ?? '' }}" placeholder="Entidad">
                <input class="form-input" type="text" name="ip" value="{{ $filters['ip'] ?? '' }}" placeholder="IP">
                <input class="form-input" type="text" name="request_id" value="{{ $filters['request_id'] ?? '' }}" placeholder="Request ID">
                <input class="form-input md:col-span-2" type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Texto libre">
                <div class="flex flex-wrap gap-2 md:col-span-4">
                    <x-ui.button type="submit" icon="search">Filtrar</x-ui.button>
                    <x-ui.button variant="outline" :href="route('administration.audit.index')">Limpiar filtros</x-ui.button>
                    @if($canExport)
                        <x-ui.button variant="secondary" :href="route('administration.audit.export', request()->query())">Exportar CSV</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>

        <div class="grid gap-3 md:hidden">
            @forelse($records as $record)
                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-black text-siafco-primary-900">{{ str($record->action)->replace(['_', '.'], ' ')->headline() }}</p>
                            <p class="text-xs text-siafco-muted">{{ $record->created_at->format('d/m/Y H:i') }} · {{ $record->user?->name ?? 'Sistema' }}</p>
                        </div>
                        <x-ui.badge variant="info">{{ $logService->moduleFor($record->action) }}</x-ui.badge>
                    </div>
                    <p class="mt-3 text-sm text-siafco-muted">{{ $sanitizer->summary($record->metadata ?? []) }}</p>
                    <div class="mt-4">
                        <x-ui.button variant="outline" :href="route('administration.audit.show', $record)">Ver detalle</x-ui.button>
                    </div>
                </x-ui.card>
            @empty
                <x-ui.empty-state title="Sin registros" message="No se encontraron eventos con los filtros seleccionados." />
            @endforelse
        </div>

        <x-ui.table class="hidden md:block">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Actor</th>
                    <th>Rol</th>
                    <th>Accion</th>
                    <th>Modulo</th>
                    <th>Entidad</th>
                    <th>IP</th>
                    <th>Resumen</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                    <tr>
                        <td>{{ $record->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $record->user?->name ?? 'Sistema' }}</td>
                        <td>{{ $record->user?->roleLabel() ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ $record->action }}</td>
                        <td>{{ $logService->moduleFor($record->action) }}</td>
                        <td>{{ class_basename($record->auditable_type ?: '') ?: '—' }} @if($record->auditable_id)#{{ $record->auditable_id }}@endif</td>
                        <td>{{ $record->ip_address ?: '—' }}</td>
                        <td>{{ $sanitizer->summary($record->metadata ?? []) }}</td>
                        <td><x-ui.button variant="outline" :href="route('administration.audit.show', $record)">Ver</x-ui.button></td>
                    </tr>
                @empty
                    <tr><td colspan="9">Sin registros de auditoria.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>

        {{ $records->links() }}
    </div>
</x-layouts.app>
