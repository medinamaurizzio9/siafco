<x-layouts.app title="Ingresar">
    <div class="mx-auto grid min-h-[80vh] max-w-5xl items-center gap-8 lg:grid-cols-2">
        <section>
            <div class="mb-5 inline-flex rounded bg-[#0b1f3a] px-3 py-1 text-sm font-bold text-[#d4af37]">Sistema Integral de Afiliacion Cooperativa</div>
            <div class="mb-5 flex items-center gap-4">
                <span class="grid h-16 w-16 place-items-center overflow-hidden rounded border border-[#d4af37] bg-white text-2xl font-black text-[#0b1f3a]">
                    @if($institution->logoUrl())
                        <img class="h-full w-full object-contain p-2" src="{{ $institution->logoUrl() }}" alt="Logo">
                    @else
                        S
                    @endif
                </span>
                <h1 class="text-4xl font-black text-[#0b1f3a] sm:text-5xl">SIAFCO</h1>
            </div>
            <p class="mt-4 text-lg text-slate-600">Gestion de afiliados, pagos, credenciales digitales y verificacion publica institucional.</p>
        </section>
        <form method="post" action="{{ route('login.post') }}" class="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            <label class="form-label">Correo</label>
            <input class="form-input" type="email" name="email" value="{{ old('email') }}" required autofocus>
            <div class="mt-4">
                <x-password-input name="password" label="Contraseña" autocomplete="current-password" />
            </div>
            <label class="mt-4 flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-emerald-700">
                Recordarme
            </label>
            <button class="btn-primary mt-6 w-full">Ingresar</button>
        </form>
    </div>
</x-layouts.app>
