<x-layouts.app title="Solicitud recibida">
    <section class="section-card mx-auto max-w-xl text-center">
        <h1 class="text-2xl font-black text-[#0b1f3a]">Pago enviado a revisión</h1>
        <p class="mt-3 text-slate-600">La activación no es automática. Conserva tu código <strong>{{ $application->request_code }}</strong>.</p>
        <a class="btn-primary mt-6" href="{{ route('public-affiliation.status', $application) }}">Consultar estado</a>
    </section>
</x-layouts.app>
