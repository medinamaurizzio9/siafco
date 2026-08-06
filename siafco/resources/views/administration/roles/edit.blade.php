<x-layouts.app :title="$title">
    <form method="post" action="{{ route('administration.roles.update', $role) }}" class="grid gap-6">
        @csrf
        @method('PATCH')

        <x-ui.card eyebrow="Matriz de permisos" :title="$roleLabel">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-siafco-muted">Solo se aceptan permisos registrados en el catalogo interno de SIAFCO.</p>
                <div class="flex flex-wrap gap-2">
                    <x-ui.button variant="outline" :href="route('administration.roles.index')">Volver</x-ui.button>
                    @if($canUpdate)
                        <x-ui.button type="submit">Guardar permisos</x-ui.button>
                    @endif
                </div>
            </div>
        </x-ui.card>

        @foreach($groups as $group => $permissions)
            <x-ui.card :title="$group">
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($permissions as $permission)
                        @php
                            $checked = in_array($permission['key'], $assigned, true);
                            $isProtected = in_array($permission['key'], $protected, true);
                        @endphp
                        <label class="flex gap-3 rounded-xl border-2 border-siafco-border bg-white p-4 transition hover:border-siafco-primary-700">
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $permission['key'] }}"
                                class="mt-1 h-5 w-5 rounded border-2 border-siafco-border text-siafco-primary-900 focus:ring-siafco-gold-500"
                                @checked($checked)
                                @disabled(! $canUpdate || $isProtected)
                            >
                            @if($isProtected)
                                <input type="hidden" name="permissions[]" value="{{ $permission['key'] }}">
                            @endif
                            <span>
                                <span class="block font-black text-siafco-primary-900">{{ $permission['label'] }}</span>
                                <span class="block font-mono text-xs text-siafco-muted">{{ $permission['key'] }}</span>
                                <span class="mt-1 block text-xs text-siafco-muted">{{ $permission['description'] }}</span>
                                <span class="mt-2 inline-flex">
                                    <x-ui.badge variant="{{ $checked ? 'success' : 'muted' }}">{{ $checked ? 'Asignado' : 'No asignado' }}</x-ui.badge>
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach
    </form>

    @if($canUpdate)
        <form method="post" action="{{ route('administration.roles.reset', $role) }}" class="mt-6">
            @csrf
            <x-ui.button type="submit" variant="outline">Restaurar configuracion base</x-ui.button>
        </form>
    @endif
</x-layouts.app>
