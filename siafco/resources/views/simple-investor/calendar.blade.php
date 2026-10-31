<x-simple-investor.layout title="Mi calendario">
    <h1 class="investor-title">Mi calendario</h1>
    <p class="investor-intro">Fechas contractuales para seguimiento. No son rendimientos ni pagos garantizados.</p>
    <section class="investor-card"><h2>Próxima fecha</h2><p>{{ $calendar['next'] ? $calendar['next']['date']->format('d/m/Y') : 'Sin próximas fechas proyectadas' }}</p></section>
    @foreach(['today' => 'Hoy', 'future' => 'Próximas fechas', 'past' => 'Fechas anteriores para revisión'] as $group => $label)
        <section><h2 class="investor-title">{{ $label }}</h2><div class="investor-list">
            @forelse($calendar[$group] as $date)
                <article class="investor-card"><small>{{ $date['contract']->contract_number }} · {{ $date['date']->timezoneName }}</small>
                    <h3>{{ $date['date']->format('d/m/Y') }}</h3>
                    <p>{{ $group === 'today' ? 'Hoy' : ($group === 'past' ? abs($date['days_remaining']).' días pasados' : $date['days_remaining'].' días restantes') }}</p>
                    <span>{{ $date['state'] }}</span>
                </article>
            @empty<div class="investor-card">Sin fechas en esta sección.</div>@endforelse
        </div></section>
    @endforeach
</x-simple-investor.layout>
