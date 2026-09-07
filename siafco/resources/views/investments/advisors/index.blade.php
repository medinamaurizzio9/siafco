<x-layouts.app title="Asesores de inversión">
    <div class="section-card">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-black text-[#0b1f3a]">Asesores de inversión</h2>
                <p class="text-sm text-slate-500">Gestión de asesores y sus accesos públicos mediante QR.</p>
            </div>
            @if(auth()->user()->hasPermission('investment_advisors.create'))
                <a class="btn-primary" href="{{ route('investments.advisors.create') }}">Nuevo asesor</a>
            @endif
        </div>

        <form class="mb-4 grid gap-3 md:grid-cols-[1fr_180px_auto]" method="get">
            <input class="form-input" name="search" value="{{ request('search') }}" placeholder="Buscar por código, nombre, celular o correo">
            <select class="form-input" name="status">
                <option value="">Todos los estados</option>
                <option value="1" @selected(request('status') === '1')>Activos</option>
                <option value="0" @selected(request('status') === '0')>Inactivos</option>
            </select>
            <button class="btn-secondary">Filtrar</button>
        </form>

        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Código</th><th>Nombre</th><th>Celular</th><th>Email</th><th>Estado</th><th>Usuario vinculado</th><th>Acciones</th></tr></thead>
                <tbody>
                @forelse($advisors as $advisor)
                    <tr>
                        <td class="font-black">{{ $advisor->advisor_number }}</td>
                        <td>{{ $advisor->full_name }}</td>
                        <td>{{ $advisor->phone }}</td>
                        <td>{{ $advisor->email ?: 'Sin correo' }}</td>
                        <td><span class="badge">{{ $advisor->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td>{{ $advisor->user?->name ?? 'Sin vincular' }}</td>
                        <td>
                            <div class="flex gap-3">
                                <a class="font-bold text-[#0b1f3a]" href="{{ route('investments.advisors.show', $advisor) }}">Ver</a>
                                @if(auth()->user()->hasPermission('investment_advisors.update'))
                                    <a class="font-bold text-[#0b1f3a]" href="{{ route('investments.advisors.edit', $advisor) }}">Editar</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-8 text-center text-slate-500">No se encontraron asesores.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $advisors->links() }}</div>
    </div>
</x-layouts.app>
