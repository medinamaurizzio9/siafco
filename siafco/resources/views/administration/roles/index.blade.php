<x-layouts.app :title="$title">
    <div class="grid gap-6">
        <x-ui.card eyebrow="Administracion" title="Roles y permisos">
            <div class="flex flex-col gap-2 text-sm text-siafco-muted sm:flex-row sm:items-center sm:justify-between">
                <p>Gestiona la matriz de permisos de roles internos. Afiliados y accionistas no se administran aqui.</p>
                <x-ui.badge variant="info">{{ $roles->count() }} roles internos</x-ui.badge>
            </div>
        </x-ui.card>

        <div class="grid gap-3 md:hidden">
            @foreach($roles as $role)
                <x-ui.card>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-black text-siafco-primary-900">{{ $role['label'] }}</p>
                            <p class="text-sm text-siafco-muted">{{ $role['description'] }}</p>
                        </div>
                        <x-ui.badge variant="{{ $role['has_override'] ? 'warning' : 'muted' }}">{{ $role['status'] }}</x-ui.badge>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs font-black uppercase text-siafco-muted">Usuarios</p>
                            <p class="text-lg font-black">{{ $role['user_count'] }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-xs font-black uppercase text-siafco-muted">Permisos</p>
                            <p class="text-lg font-black">{{ $role['permission_count'] }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-ui.button variant="outline" :href="route('administration.roles.edit', $role['key'])">Ver permisos</x-ui.button>
                        @if(auth()->user()->hasPermission('roles.update'))
                            <x-ui.button :href="route('administration.roles.edit', $role['key'])">Editar permisos</x-ui.button>
                        @endif
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.table class="hidden md:block">
            <thead>
                <tr>
                    <th>Rol</th>
                    <th>Descripcion</th>
                    <th>Usuarios</th>
                    <th>Permisos</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td class="font-black text-siafco-primary-900">{{ $role['label'] }}</td>
                        <td>{{ $role['description'] }}</td>
                        <td>{{ $role['user_count'] }}</td>
                        <td>{{ $role['permission_count'] }}</td>
                        <td><x-ui.badge variant="{{ $role['has_override'] ? 'warning' : 'muted' }}">{{ $role['status'] }}</x-ui.badge></td>
                        <td>
                            <div class="flex justify-end gap-2">
                                <x-ui.button variant="outline" :href="route('administration.roles.edit', $role['key'])">Ver permisos</x-ui.button>
                                @if(auth()->user()->hasPermission('roles.update'))
                                    <x-ui.button :href="route('administration.roles.edit', $role['key'])">Editar</x-ui.button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </x-ui.table>
    </div>
</x-layouts.app>
