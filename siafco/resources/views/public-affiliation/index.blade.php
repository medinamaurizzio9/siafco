<x-layouts.app title="Afiliación en línea">
    <div class="mx-auto max-w-3xl">
        <header class="border-b-4 border-[#d4af37] bg-[#0b1f3a] px-6 py-8 text-white">
            <p class="text-sm font-bold text-[#d4af37]">{{ $institution->institution_name }}</p>
            <h1 class="mt-2 text-3xl font-black">Afiliación en línea</h1>
            <p class="mt-3 max-w-2xl text-slate-200">Registra tus datos, elige un plan y presenta la referencia de tu transferencia para revisión de Secretaría.</p>
        </header>
        <section class="bg-white px-6 py-8 shadow-sm">
            <ol class="grid gap-4 sm:grid-cols-3">
                <li><strong class="block text-[#0b1f3a]">1. Registro</strong><span class="text-sm text-slate-600">Datos personales y plan.</span></li>
                <li><strong class="block text-[#0b1f3a]">2. Pago</strong><span class="text-sm text-slate-600">Transferencia y referencia.</span></li>
                <li><strong class="block text-[#0b1f3a]">3. Revisión</strong><span class="text-sm text-slate-600">Activación manual y credencial.</span></li>
            </ol>
            <a class="btn-primary mt-8 min-h-12 w-full sm:w-auto" href="{{ route('public-affiliation.create') }}">Iniciar solicitud</a>
        </section>
    </div>
</x-layouts.app>
