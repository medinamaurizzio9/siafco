<x-layouts.app title="QR público de afiliación">
    <section class="section-card mx-auto max-w-2xl text-center">
        <h2 class="text-2xl font-black text-[#0b1f3a]">QR público de afiliación</h2>
        <p class="mt-2 text-slate-600">Este QR abre el formulario público. No es el QR bancario ni el de credencial.</p>
        <img class="mx-auto mt-6 w-full max-w-md" src="{{ $qrUrl }}" alt="QR público de afiliación">
        <p class="mt-3 break-all text-sm">{{ config('siafco.public_affiliation_url') }}</p>
        <div class="mt-6 flex flex-wrap justify-center gap-3"><a class="btn-primary" href="{{ route('public-affiliation.qr.png') }}">Descargar PNG</a><a class="btn-secondary" href="{{ route('public-affiliation.qr.pdf') }}">Descargar PDF</a><button class="btn-secondary" type="button" onclick="window.print()">Imprimir</button></div>
    </section>
</x-layouts.app>
