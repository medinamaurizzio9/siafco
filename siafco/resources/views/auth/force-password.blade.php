<x-layouts.app title="Actualizar contraseña">
    <div class="mx-auto max-w-lg">
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="border-b border-slate-200 pb-4">
                <p class="text-xs font-black uppercase text-[#b8942f]">Seguridad de la cuenta</p>
                <h2 class="mt-1 text-2xl font-black text-[#0b1f3a]">ACTUALIZA TU CONTRASEÑA</h2>
                <p class="mt-2 text-sm text-slate-600">Por seguridad, debes crear una nueva contraseña antes de continuar.</p>
            </div>
            <form class="mt-5 grid gap-4" method="post" action="{{ route('password.force.update') }}">
                @csrf
                @method('PATCH')
                <x-password-input name="password" label="Nueva contraseña" autocomplete="new-password" minlength="8" />
                <x-password-input name="password_confirmation" label="Confirmar nueva contraseña" autocomplete="new-password" minlength="8" />
                <p class="text-sm text-slate-500">Usa al menos 8 caracteres, incluyendo letras y números.</p>
                <button class="btn-primary mt-2 py-3" type="submit">GUARDAR NUEVA CONTRASEÑA</button>
            </form>
        </section>
    </div>
</x-layouts.app>
