<x-layouts.app title="Usuarios internos">
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase text-[#b8942f]">Administración</p>
                <h2 class="mt-1 text-2xl font-black text-[#0b1f3a]">USUARIOS INTERNOS</h2>
                <p class="mt-1 text-sm text-slate-600">Administra unicamente al personal interno con acceso administrativo a SIAFCO. Los afiliados se gestionan desde su ficha.</p>
            </div>
            @can('create', App\Models\User::class)
                <a class="btn-primary w-full sm:w-auto" href="{{ route('admin.users.create') }}">NUEVO USUARIO INTERNO</a>
            @endcan
        </header>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="metric-card"><p>Internos activos</p><strong>{{ $metrics['active'] }}</strong></div>
            <div class="metric-card"><p>Internos bloqueados</p><strong>{{ $metrics['blocked'] }}</strong></div>
            <div class="metric-card"><p>Internos eliminados</p><strong>{{ $metrics['deleted'] }}</strong></div>
            <div class="metric-card"><p>Accesos recientes</p><strong>{{ $metrics['recent'] }}</strong></div>
        </div>

        <div class="section-card">
            <form method="get" class="grid gap-3 md:grid-cols-4">
                <label class="md:col-span-2">
                    <span class="form-label">Buscar</span>
                    <input class="form-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Nombre, usuario, correo o CI">
                </label>
                <label>
                    <span class="form-label">Rol</span>
                    <select class="form-input" name="role">
                        <option value="">Todos</option>
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }} ({{ $roleCounts[$value] ?? 0 }})</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="form-label">Estado</span>
                    <select class="form-input" name="status">
                        <option value="">Todos vigentes</option>
                        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Activo</option>
                        <option value="blocked" @selected(($filters['status'] ?? '') === 'blocked')>Bloqueado</option>
                        <option value="deleted" @selected(($filters['status'] ?? '') === 'deleted')>Eliminado</option>
                    </select>
                </label>
                <div class="flex flex-col gap-2 sm:flex-row md:col-span-4 md:justify-end">
                    <a class="btn-secondary" href="{{ route('admin.users.index') }}">LIMPIAR FILTROS</a>
                    <button class="btn-primary" type="submit">FILTRAR</button>
                </div>
            </form>
        </div>

        <div class="section-card hidden overflow-x-auto p-0 md:block">
            <table class="table min-w-[1320px]">
                <thead><tr>
                    <th>Personal</th><th>Usuario</th><th>Correo / CI</th><th>Cargo</th>
                    <th>Rol</th><th>Estado</th><th>Último acceso</th><th>Creación</th><th>Acciones</th>
                </tr></thead>
                <tbody>
                @forelse($users as $internalUser)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                @if($internalUser->photoUrl())
                                    <img class="h-10 w-10 rounded object-cover" src="{{ $internalUser->photoUrl() }}" alt="">
                                @else
                                    <span class="grid h-10 w-10 place-items-center rounded bg-[#0b1f3a] font-black text-[#d4af37]">{{ str($internalUser->name)->substr(0, 1)->upper() }}</span>
                                @endif
                                <strong class="max-w-48">{{ $internalUser->name }}</strong>
                            </div>
                        </td>
                        <td>{{ $internalUser->username }}</td>
                        <td><span class="block">{{ $internalUser->email }}</span><span class="text-xs text-slate-500">{{ $internalUser->ci }}</span></td>
                        <td>{{ $internalUser->position ?: 'Sin cargo' }}</td>
                        <td><span class="badge">{{ $internalUser->roleLabel() }}</span></td>
                        <td>
                            <span class="badge {{ $internalUser->trashed() || !$internalUser->is_active ? '!bg-red-100 !text-red-800' : '!bg-emerald-100 !text-emerald-800' }}">
                                {{ $internalUser->trashed() ? 'Eliminado' : ($internalUser->is_active ? 'Activo' : 'Bloqueado') }}
                            </span>
                        </td>
                        <td>{{ $internalUser->last_login_at?->diffForHumans() ?? 'Nunca ingresó' }}</td>
                        <td>{{ $internalUser->created_at->format('d/m/Y') }}</td>
                        <td>
                            @if($internalUser->trashed())
                                @can('restore', $internalUser)
                                    <form method="post" action="{{ route('admin.users.restore', $internalUser->id) }}">@csrf<button class="font-bold text-[#0b1f3a] underline">Restaurar</button></form>
                                @endcan
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @can('view', $internalUser)<a class="rounded bg-slate-100 px-2 py-1 text-xs font-black text-[#0b1f3a]" href="{{ route('admin.users.show', $internalUser) }}">Ver</a>@endcan
                                    @can('update', $internalUser)<a class="rounded bg-slate-100 px-2 py-1 text-xs font-black text-[#0b1f3a]" href="{{ route('admin.users.edit', $internalUser) }}">Editar</a>@endcan
                                    @can('block', $internalUser)<form method="post" action="{{ route('admin.users.block', $internalUser) }}">@csrf<button class="rounded bg-red-50 px-2 py-1 text-xs font-black text-red-800">Bloquear</button></form>@endcan
                                    @can('activate', $internalUser)<form method="post" action="{{ route('admin.users.activate', $internalUser) }}">@csrf<button class="rounded bg-emerald-50 px-2 py-1 text-xs font-black text-emerald-800">Activar</button></form>@endcan
                                    @can('resetPassword', $internalUser)<a class="rounded bg-amber-50 px-2 py-1 text-xs font-black text-amber-900" href="{{ route('admin.users.show', $internalUser) }}#acciones-acceso">Restablecer</a>@endcan
                                    @can('delete', $internalUser)<a class="rounded bg-red-50 px-2 py-1 text-xs font-black text-red-800" href="{{ route('admin.users.show', $internalUser) }}#acciones-acceso">Eliminar</a>@endcan
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-10 text-center text-slate-500">No se encontraron usuarios internos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden">
            @forelse($users as $internalUser)
                <article class="section-card">
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded bg-[#0b1f3a] font-black text-[#d4af37]">
                            @if($internalUser->photoUrl())<img class="h-full w-full object-cover" src="{{ $internalUser->photoUrl() }}" alt="">@else{{ str($internalUser->name)->substr(0, 1)->upper() }}@endif
                        </span>
                        <div class="min-w-0">
                            <h3 class="break-words font-black text-[#0b1f3a]">{{ $internalUser->name }}</h3>
                            <p class="break-all text-sm text-slate-600">{{ $internalUser->email }}</p>
                            <p class="text-xs text-slate-500">{{ $internalUser->username }} · {{ $internalUser->ci }}</p>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span class="badge">{{ $internalUser->roleLabel() }}</span>
                        <span class="badge {{ $internalUser->trashed() || !$internalUser->is_active ? '!bg-red-100 !text-red-800' : '!bg-emerald-100 !text-emerald-800' }}">{{ $internalUser->trashed() ? 'Eliminado' : ($internalUser->is_active ? 'Activo' : 'Bloqueado') }}</span>
                    </div>
                    <p class="mt-3 text-xs text-slate-500">Último acceso: {{ $internalUser->last_login_at?->diffForHumans() ?? 'Nunca ingresó' }}</p>
                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                        @if($internalUser->trashed())
                            @can('restore', $internalUser)<form method="post" action="{{ route('admin.users.restore', $internalUser->id) }}">@csrf<button class="btn-secondary">RESTAURAR</button></form>@endcan
                        @else
                            @can('view', $internalUser)<a class="btn-secondary" href="{{ route('admin.users.show', $internalUser) }}">VER</a>@endcan
                            @can('update', $internalUser)<a class="btn-primary" href="{{ route('admin.users.edit', $internalUser) }}">EDITAR</a>@endcan
                            @can('block', $internalUser)<form method="post" action="{{ route('admin.users.block', $internalUser) }}">@csrf<button class="btn-danger">BLOQUEAR</button></form>@endcan
                            @can('activate', $internalUser)<form method="post" action="{{ route('admin.users.activate', $internalUser) }}">@csrf<button class="btn-primary">ACTIVAR</button></form>@endcan
                            @can('resetPassword', $internalUser)<a class="btn-secondary" href="{{ route('admin.users.show', $internalUser) }}#acciones-acceso">RESTABLECER</a>@endcan
                        @endif
                    </div>
                </article>
            @empty
                <div class="section-card text-center text-slate-500">No se encontraron usuarios internos.</div>
            @endforelse
        </div>

        {{ $users->links() }}
    </div>
</x-layouts.app>
