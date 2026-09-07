<x-layouts.app title="CRM de inversiones">
    <div class="crm-page">
        <section class="crm-hero">
            <p class="crm-eyebrow">CRM de inversiones</p>
            <h2 class="crm-title">Panel de seguimiento comercial</h2>
            <p class="mt-2 text-blue-100">Prospectos, transiciones y seguimientos con información real del CRM.</p>
        </section>

        <section class="crm-grid crm-metrics mt-5">
            @foreach([
                'Prospectos activos' => 'total',
                'Captados' => 'captured',
                'En transición' => 'in_transition',
                'Cerrados' => 'closed',
                'Perdidos' => 'lost',
                'Captados hoy' => 'today',
                'Seguimientos hoy' => 'follow_today',
                'Seguimientos vencidos' => 'overdue',
            ] as $label => $key)
                <article class="crm-card crm-metric">
                    <p class="crm-metric-label">{{ $label }}</p>
                    <p class="crm-metric-value">{{ $counts[$key] }}</p>
                </article>
            @endforeach
        </section>

        <section class="crm-card mt-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="crm-eyebrow text-[#0b5fb3]">Calidad de atención</p>
                    <h3 class="crm-section-title">Valoraciones de prospectos</h3>
                </div>
                <a class="crm-button crm-button--secondary" href="{{ route('investments.prospects.index') }}">Ver prospectos</a>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach($quality as $rating => $item)
                    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <strong>{{ \App\Support\InvestmentProspectServiceRating::emoji($rating) }} {{ $item['label'] }}</strong>
                            <span class="text-2xl font-black text-[#0b1f3a]">{{ $item['count'] }}</span>
                        </div>
                        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-[#d9a514]" style="width: {{ $item['percentage'] }}%"></div></div>
                        <p class="mt-2 text-sm text-slate-500">{{ $item['percentage'] }}% de las valoraciones</p>
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-layouts.app>
