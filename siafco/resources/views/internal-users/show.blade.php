<x-layouts.app title="Detalle de usuario">
    <div class="space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase text-[#b8942f]">Usuario interno</p>
                <h2 class="text-2xl font-black text-[#0b1f3a]">{{ $internalUser->name }}</h2>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row">
                <a class="btn-secondary" href="{{ route('admin.users.index') }}">VOLVER</a>
                @can('update', $internalUser)<a class="btn-primary" href="{{ route('admin.users.edit', $internalUser) }}">EDITAR</a>@endcan
            </div>
        </header>

        <section class="section-card">
            <div class="grid gap-6 lg:grid-cols-[180px_1fr]">
                <div>
                    @if($internalUser->photoUrl())
                        <img class="aspect-square w-full rounded object-cover" src="{{ $internalUser->photoUrl() }}" alt="Fotografía de {{ $internalUser->name }}">
                    @else
                        <div class="grid aspect-square w-full place-items-center rounded bg-[#0b1f3a] text-5xl font-black text-[#d4af37]">{{ str($internalUser->name)->substr(0, 1)->upper() }}</div>
                    @endif
                </div>
                <dl class="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach([
                        'Cédula de identidad' => $internalUser->ci,
                        'Usuario' => $internalUser->username,
                        'Correo' => $internalUser->email,
                        'Celular' => $internalUser->phone ?: 'No registrado',
                        'Cargo' => $internalUser->position ?: 'No registrado',
                        'Área' => $internalUser->area ?: 'No registrada',
                        'Rol' => $internalUser->roleLabel(),
                        'Estado' => $internalUser->is_active ? 'Activo' : 'Bloqueado',
                        'Último acceso' => $internalUser->last_login_at?->format('d/m/Y H:i') ?? 'Nunca ingresó',
                        'IP último acceso' => $internalUser->last_login_ip ?: 'No registrada',
                        'Creación' => $internalUser->created_at->format('d/m/Y H:i'),
                        'Actualización' => $internalUser->updated_at->format('d/m/Y H:i'),
                    ] as $label => $value)
                        <div><dt class="text-xs font-black uppercase text-slate-500">{{ $label }}</dt><dd class="mt-1 break-words font-bold text-slate-900">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </section>

        <section class="section-card">
            <h3 class="text-lg font-black text-[#0b1f3a]">ACCIONES DE ACCESO</h3>
            <div class="mt-4 grid gap-4 lg:grid-cols-3">
                @can('block', $internalUser)
                    <form method="post" action="{{ route('admin.users.block', $internalUser) }}" class="rounded border border-slate-200 p-4">@csrf
                        <strong class="block text-slate-900">Bloquear usuario</strong><p class="my-3 text-sm text-slate-600">Cierra sus sesiones y evita nuevos ingresos.</p><button class="btn-danger w-full">BLOQUEAR</button>
                    </form>
                @endcan
                @can('activate', $internalUser)
                    <form method="post" action="{{ route('admin.users.activate', $internalUser) }}" class="rounded border border-slate-200 p-4">@csrf
                        <strong class="block text-slate-900">Activar usuario</strong><p class="my-3 text-sm text-slate-600">Habilita nuevamente el acceso al sistema.</p><button class="btn-primary w-full">ACTIVAR</button>
                    </form>
                @endcan
                @can('resetPassword', $internalUser)
                    <form method="post" action="{{ route('admin.users.password.reset', $internalUser) }}" class="rounded border border-slate-200 p-4">@csrf
                        <strong class="block">Restablecer contraseña</strong><p class="my-2 text-sm text-slate-600">Escriba RESTABLECER para confirmar.</p>
                        <input class="form-input mb-3" name="confirmation" autocomplete="off" required><button class="btn-secondary w-full">RESTABLECER CONTRASEÑA</button>
                    </form>
                @endcan
                @can('delete', $internalUser)
                    <form method="post" action="{{ route('admin.users.destroy', $internalUser) }}" class="rounded border border-red-200 p-4">@csrf @method('DELETE')
                        <strong class="block text-red-800">Eliminar usuario</strong><p class="my-2 text-sm text-slate-600">Escriba ELIMINAR para confirmar.</p>
                        <input class="form-input mb-3" name="confirmation" autocomplete="off" required><button class="btn-danger w-full">ELIMINAR</button>
                    </form>
                @endcan
            </div>
        </section>

        <section class="section-card">
            <h3 class="text-lg font-black text-[#0b1f3a]">HISTORIAL DE ACCIONES</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Fecha</th><th>Acción</th><th>IP</th><th>Resultado</th></tr></thead>
                    <tbody>
                    @forelse($history as $event)
                        <tr><td>{{ $event->created_at->format('d/m/Y H:i') }}</td><td>{{ str($event->action)->replace('_', ' ')->upper() }}</td><td>{{ $event->ip_address ?: '—' }}</td><td>{{ data_get($event->metadata, 'result', 'registrado') }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-500">Sin acciones registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts.app>
