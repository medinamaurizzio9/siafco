<x-layouts.app title="Confirmar cierre de sesión">
    <div class="mx-auto max-w-md rounded bg-white p-6 shadow">
        <h1 class="text-xl font-black text-[#0b1f3a]">Confirmar cierre de sesión</h1>
        <p class="mt-3 text-sm text-slate-700">
            El formulario anterior expiró. Para proteger tu cuenta, confirma nuevamente que deseas cerrar sesión.
        </p>

        <form method="post" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button class="w-full rounded bg-[#102b4c] px-4 py-2 font-semibold text-white hover:bg-[#163b68]">
                Cerrar sesión
            </button>
        </form>

        <a href="{{ auth()->user()?->role === 'afiliado' ? route('affiliate.panel') : route('admin.dashboard') }}"
           class="mt-4 block text-center text-sm font-semibold text-[#102b4c] hover:underline">
            Volver al sistema
        </a>
    </div>
</x-layouts.app>
