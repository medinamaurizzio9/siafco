<x-layouts.app :title="$title">
    <div class="grid gap-6">
        <x-ui.card eyebrow="Evento de auditoria" :title="str($audit->action)->replace(['_', '.'], ' ')->headline()">
            <div class="grid gap-3 text-sm md:grid-cols-3">
                <div><p class="font-black text-siafco-muted">Fecha</p><p>{{ $audit->created_at->format('d/m/Y H:i:s') }}</p></div>
                <div><p class="font-black text-siafco-muted">Actor</p><p>{{ $audit->user?->name ?? 'Sistema' }}</p></div>
                <div><p class="font-black text-siafco-muted">Rol</p><p>{{ $audit->user?->roleLabel() ?? '—' }}</p></div>
                <div><p class="font-black text-siafco-muted">Modulo</p><p>{{ $module }}</p></div>
                <div><p class="font-black text-siafco-muted">Entidad</p><p>{{ class_basename($audit->auditable_type ?: '') ?: '—' }} @if($audit->auditable_id)#{{ $audit->auditable_id }}@endif</p></div>
                <div><p class="font-black text-siafco-muted">IP</p><p>{{ $audit->ip_address ?: '—' }}</p></div>
            </div>
        </x-ui.card>

        <x-ui.card title="Metadata segura">
            @php
                $oldValues = $metadata['old_values'] ?? [];
                $newValues = $metadata['new_values'] ?? [];
            @endphp
            @if(is_array($oldValues) && is_array($newValues) && ($oldValues || $newValues))
                <x-ui.table>
                    <thead><tr><th>Campo</th><th>Antes</th><th>Despues</th></tr></thead>
                    <tbody>
                        @foreach(array_unique([...array_keys($oldValues), ...array_keys($newValues)]) as $field)
                            <tr>
                                <td>{{ $field }}</td>
                                <td>{{ is_scalar($oldValues[$field] ?? null) ? ($oldValues[$field] ?? '—') : json_encode($oldValues[$field] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                                <td>{{ is_scalar($newValues[$field] ?? null) ? ($newValues[$field] ?? '—') : json_encode($newValues[$field] ?? null, JSON_UNESCAPED_UNICODE) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            @endif

            <pre class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-white">{{ json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-ui.card>

        <x-ui.button variant="outline" :href="route('administration.audit.index')">Volver</x-ui.button>
    </div>
</x-layouts.app>
