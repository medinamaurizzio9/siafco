<x-layouts.app :title="$internalUser->exists ? 'Editar usuario interno' : 'Nuevo usuario interno'">
    @php($editing = $internalUser->exists)
    <form method="post" enctype="multipart/form-data" action="{{ $editing ? route('admin.users.update', $internalUser) : route('admin.users.store') }}" class="space-y-6">
        @csrf
        @if($editing) @method('PATCH') @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-black uppercase text-[#b8942f]">Administración de accesos</p>
                <h2 class="text-2xl font-black text-[#0b1f3a]">{{ $editing ? 'EDITAR USUARIO INTERNO' : 'NUEVO USUARIO INTERNO' }}</h2>
                <p class="mt-1 text-sm text-slate-600">Este modulo administra solo personal interno. Las cuentas de afiliados se gestionan desde Afiliacion > Afiliados.</p>
            </div>
            <a class="btn-secondary" href="{{ $editing ? route('admin.users.show', $internalUser) : route('admin.users.index') }}">CANCELAR</a>
        </div>

        <section class="section-card">
            <h3 class="border-b border-slate-200 pb-3 text-lg font-black text-[#0b1f3a]">DATOS PERSONALES</h3>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <label><span class="form-label">Nombre completo</span><input class="form-input" name="name" value="{{ old('name', $internalUser->name) }}" required></label>
                <label><span class="form-label">Cédula de identidad</span><input class="form-input" name="ci" value="{{ old('ci', $internalUser->ci) }}" required></label>
                <label><span class="form-label">Celular</span><input class="form-input" name="phone" value="{{ old('phone', $internalUser->phone) }}"></label>
                <label><span class="form-label">Correo electrónico</span><input class="form-input" type="email" name="email" value="{{ old('email', $internalUser->email) }}" required></label>
                <label><span class="form-label">Cargo</span><input class="form-input" name="position" value="{{ old('position', $internalUser->position) }}"></label>
                <label><span class="form-label">Área</span>
                    <select class="form-input" name="area"><option value="">Sin especificar</option>
                        @foreach($areas as $area)<option @selected(old('area', $internalUser->area) === $area)>{{ $area }}</option>@endforeach
                    </select>
                </label>
                <label class="md:col-span-2"><span class="form-label">Fotografía (JPG, PNG o WEBP; máximo 5 MB)</span><input class="form-input" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp"></label>
            </div>
        </section>

        <section class="section-card">
            <h3 class="border-b border-slate-200 pb-3 text-lg font-black text-[#0b1f3a]">CUENTA DE ACCESO</h3>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <label><span class="form-label">Usuario</span><input class="form-input" name="username" value="{{ old('username', $internalUser->username) }}" required></label>
                <label><span class="form-label">Rol principal</span>
                    <select class="form-input" name="role" required>
                        @foreach($roles as $value => $label)<option value="{{ $value }}" @selected(old('role', $internalUser->role) === $value)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                @unless($editing)
                    <label class="flex items-center gap-3 md:col-span-2">
                        <input type="hidden" name="is_active" value="0"><input class="h-5 w-5 accent-[#0b1f3a]" type="checkbox" name="is_active" value="1" @checked(old('is_active', true))>
                        <span class="font-bold text-slate-700">Cuenta activa</span>
                    </label>
                    <label class="flex items-center gap-3 md:col-span-2">
                        <input type="hidden" name="use_ci_password" value="0"><input class="h-5 w-5 accent-[#0b1f3a]" type="checkbox" name="use_ci_password" value="1" @checked(old('use_ci_password')) data-ci-password>
                        <span class="font-bold text-slate-700">Usar CI como contraseña temporal</span>
                    </label>
                    <div class="contents" data-password-fields>
                        <x-password-input name="password" label="Contrasena temporal" :required="false" autocomplete="new-password" />
                        <x-password-input name="password_confirmation" label="Confirmar contrasena" :required="false" autocomplete="new-password" />
                    </div>
                @endunless
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a class="btn-secondary" href="{{ $editing ? route('admin.users.show', $internalUser) : route('admin.users.index') }}">CANCELAR</a>
            <button class="btn-primary" type="submit">{{ $editing ? 'GUARDAR CAMBIOS' : 'CREAR USUARIO' }}</button>
        </div>
    </form>
    @unless($editing)
        @push('scripts')
            <script>
                (() => {
                    const toggle = document.querySelector('[data-ci-password]');
                    const fields = document.querySelector('[data-password-fields]');
                    const sync = () => {
                        fields.classList.toggle('hidden', toggle.checked);
                        fields.querySelectorAll('input').forEach(input => input.required = !toggle.checked);
                    };
                    toggle.addEventListener('change', sync);
                    sync();
                })();
            </script>
        @endpush
    @endunless
</x-layouts.app>
