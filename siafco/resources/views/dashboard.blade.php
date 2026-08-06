@php
    $user = auth()->user();
    $firstName = str($user->name)->before(' ')->toString();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Buenos dias' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
    $roleLabel = str_replace(' (legado)', '', $user->roleLabel());
    $currency = 'BOB';
    $canAudit = $user->hasPermission('audit.view') && Route::has('administration.audit.index');
    $statusDistribution = collect($metrics['affiliationStatusDistribution'] ?? []);
    $statusLabels = $statusDistribution->keys()
        ->map(fn ($status) => \App\Support\AffiliationStatusPresenter::label($status))
        ->all();
    $hasSparkline = fn (array $series) => collect($series)->filter(fn ($value) => (float) $value > 0)->count() >= 1;
    $dashboardCharts = [
        'affiliations' => [
            'type' => 'area',
            'name' => 'Afiliaciones',
            'labels' => data_get($metrics, 'affiliationTrend.labels', []),
            'series' => data_get($metrics, 'affiliationTrend.series', []),
            'color' => '#2563eb',
        ],
        'revenue' => [
            'type' => 'area',
            'name' => 'Recaudacion',
            'labels' => data_get($metrics, 'revenueTrend.labels', []),
            'series' => data_get($metrics, 'revenueTrend.series', []),
            'currency' => $currency,
            'color' => '#059669',
        ],
        'statuses' => [
            'type' => 'donut',
            'name' => 'Estados',
            'labels' => $statusLabels,
            'series' => $statusDistribution->values()->all(),
        ],
    ];
    $sparklineCharts = [
        'affiliates' => ['series' => data_get($metrics, 'affiliationTrend.series', []), 'color' => '#2563eb'],
        'revenue' => ['series' => data_get($metrics, 'revenueTrend.series', []), 'color' => '#059669'],
        'credentials' => ['series' => data_get($metrics, 'credentialTrend.series', []), 'color' => '#d97706'],
        'store' => ['series' => data_get($metrics, 'storeOrderTrend.series', []), 'color' => '#7c3aed'],
        'accesses' => ['series' => data_get($metrics, 'accessTrend.series', []), 'color' => '#0891b2'],
        'payments' => ['series' => data_get($metrics, 'revenueTrend.series', []), 'color' => '#ea580c'],
    ];
    $kpis = collect([
        [
            'key' => 'affiliates',
            'title' => 'Afiliados activos',
            'value' => (int) ($metrics['active_affiliates'] ?? 0),
            'display' => number_format((int) ($metrics['active_affiliates'] ?? 0)),
            'hint' => ((int) ($metrics['newAffiliates'] ?? 0)).' hoy',
            'icon' => 'user',
            'route' => 'affiliates.index',
            'permission' => 'affiliates.view',
            'tone' => 'blue',
        ],
        [
            'key' => 'revenue',
            'title' => 'Recaudacion',
            'value' => (float) ($metrics['confirmed_revenue'] ?? 0),
            'display' => $currency.' '.number_format((float) ($metrics['confirmed_revenue'] ?? 0), 2),
            'hint' => $currency.' '.number_format((float) ($metrics['todayRevenue'] ?? 0), 2).' hoy',
            'icon' => 'credit-card',
            'route' => 'payments.index',
            'permission' => 'payments.view',
            'tone' => 'green',
        ],
        [
            'key' => 'payments',
            'title' => 'Pagos hoy',
            'value' => (int) ($metrics['today_payments'] ?? 0),
            'display' => number_format((int) ($metrics['today_payments'] ?? 0)),
            'hint' => ((int) ($metrics['pending_payments'] ?? 0)).' pendientes',
            'icon' => 'credit-card',
            'route' => 'payments.index',
            'permission' => 'payments.view',
            'tone' => 'orange',
        ],
        [
            'key' => 'credentials',
            'title' => 'Credenciales',
            'value' => (int) ($metrics['issued_credentials'] ?? 0),
            'display' => number_format((int) ($metrics['issued_credentials'] ?? 0)),
            'hint' => ((int) ($metrics['pending_credentials'] ?? 0)).' por emitir',
            'icon' => 'briefcase',
            'route' => 'credentials.index',
            'permission' => 'credentials.view',
            'tone' => 'gold',
        ],
        [
            'key' => 'store',
            'title' => 'Pedidos tienda',
            'value' => (int) ($metrics['pending_store_orders'] ?? 0),
            'display' => number_format((int) ($metrics['pending_store_orders'] ?? 0)),
            'hint' => 'requieren atencion',
            'icon' => 'inbox',
            'route' => 'admin.store.orders.index',
            'permission' => 'store.view',
            'tone' => 'violet',
        ],
        [
            'key' => 'accesses',
            'title' => 'Usuarios internos',
            'value' => (int) ($metrics['active_internal_users'] ?? 0),
            'display' => number_format((int) ($metrics['active_internal_users'] ?? 0)),
            'hint' => ((int) ($metrics['recent_accesses'] ?? 0)).' accesos 24 h',
            'icon' => 'user',
            'route' => 'admin.users.index',
            'permission' => 'users.view',
            'tone' => 'cyan',
        ],
    ])->filter(fn ($kpi) => $user->hasPermission($kpi['permission']) && Route::has($kpi['route']))
        ->take(5)
        ->values();
    $alerts = collect([
        [
            'label' => 'Pagos',
            'value' => (int) ($metrics['pending_payments'] ?? 0),
            'hint' => 'Por revisar',
            'route' => 'payments.index',
            'permission' => 'payments.view',
            'tone' => 'orange',
        ],
        [
            'label' => 'Afiliaciones',
            'value' => (int) ($metrics['pending_affiliations'] ?? 0),
            'hint' => 'Solicitudes pendientes',
            'route' => 'public-affiliation.admin.index',
            'permission' => 'affiliates.view',
            'tone' => 'yellow',
        ],
        [
            'label' => 'Credenciales',
            'value' => (int) ($metrics['pending_credentials'] ?? 0),
            'hint' => 'Por emitir',
            'route' => 'credentials.index',
            'permission' => 'credentials.view',
            'tone' => 'blue',
        ],
        [
            'label' => 'Tienda',
            'value' => (int) ($metrics['pending_store_orders'] ?? 0),
            'hint' => 'Pedidos abiertos',
            'route' => 'admin.store.orders.index',
            'permission' => 'store.view',
            'tone' => 'green',
        ],
    ])->filter(fn ($alert) => $alert['value'] > 0 && $user->hasPermission($alert['permission']) && Route::has($alert['route']))
        ->take(4)
        ->values();
    $notificationCount = $alerts->sum('value');
@endphp

<x-layouts.app title="Centro de operaciones">
    <div class="dashboard-shell" data-dashboard-shell data-dashboard-charts='@json($dashboardCharts)' data-dashboard-sparklines='@json($sparklineCharts)'>
        <section class="dashboard-hero" aria-labelledby="dashboard-title">
            <div class="min-w-0">
                <p class="dashboard-kicker">{{ now()->translatedFormat('l, d \\d\\e F \\d\\e Y') }}</p>
                <h2 id="dashboard-title" class="dashboard-title">Centro de operaciones</h2>
                <p class="dashboard-greeting">{{ $greeting }}, {{ $firstName }}</p>
            </div>

            <div class="dashboard-hero-actions">
                <label class="dashboard-search">
                    <span class="sr-only">Buscar en SIAFCO</span>
                    <x-ui.icon name="search" class="h-4 w-4 text-siafco-muted" />
                    <input disabled placeholder="Busqueda global preparada" aria-label="Busqueda global preparada">
                </label>
                <button type="button" class="dashboard-fullscreen" data-dashboard-fullscreen aria-label="Activar pantalla completa" aria-pressed="false">
                    <x-ui.icon name="chart" class="h-4 w-4" />
                    <span>Pantalla completa</span>
                </button>
                <details class="dashboard-notifications">
                    <summary aria-label="Ver resumen de notificaciones">
                        <x-ui.icon name="bell" class="h-5 w-5" />
                        @if($notificationCount > 0)
                            <span>{{ $notificationCount }}</span>
                        @endif
                    </summary>
                    <div class="dashboard-notifications-menu">
                        @forelse($alerts as $alert)
                            <a href="{{ route($alert['route']) }}">{{ $alert['value'] }} {{ $alert['hint'] }}</a>
                        @empty
                            <p>Todo al dia</p>
                        @endforelse
                    </div>
                </details>
                <div class="dashboard-user">
                    <span aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                    <div>
                        <strong>{{ $user->name }}</strong>
                        <small>{{ $roleLabel }}</small>
                    </div>
                </div>
            </div>
        </section>

        @if($kpis->isNotEmpty())
            <section class="dashboard-kpis" aria-label="Indicadores principales">
                @foreach($kpis as $kpi)
                    <a class="dashboard-kpi dashboard-tone-{{ $kpi['tone'] }}" href="{{ route($kpi['route']) }}" aria-label="{{ $kpi['title'] }}: {{ $kpi['display'] }}">
                        <span class="dashboard-kpi-top">
                            <span class="dashboard-kpi-icon"><x-ui.icon :name="$kpi['icon']" class="h-5 w-5" /></span>
                            <span class="dashboard-kpi-chip">{{ $kpi['hint'] }}</span>
                        </span>
                        <span class="dashboard-kpi-label">{{ $kpi['title'] }}</span>
                        <strong>{{ $kpi['display'] }}</strong>
                        @if($hasSparkline($sparklineCharts[$kpi['key']]['series'] ?? []))
                            <span class="dashboard-sparkline" data-sparkline="{{ $kpi['key'] }}" aria-hidden="true"></span>
                        @endif
                    </a>
                @endforeach
            </section>
        @endif

        <section class="dashboard-attention" aria-labelledby="attention-title">
            <div>
                <p class="dashboard-kicker">Operacion</p>
                <h3 id="attention-title">Atencion inmediata</h3>
            </div>
            <div class="dashboard-alerts">
                @forelse($alerts as $alert)
                    <a class="dashboard-alert dashboard-alert-{{ $alert['tone'] }}" href="{{ route($alert['route']) }}" aria-label="{{ $alert['value'] }} {{ $alert['label'] }} {{ $alert['hint'] }}">
                        <strong>{{ $alert['value'] }} {{ $alert['label'] }}</strong>
                        <span>{{ $alert['hint'] }}</span>
                    </a>
                @empty
                    <div class="dashboard-alert dashboard-alert-green" role="status">
                        <strong>Todo al dia</strong>
                        <span>No hay acciones urgentes</span>
                    </div>
                @endforelse
            </div>
        </section>

        <section class="dashboard-chart-grid" aria-label="Graficos operativos">
            <article class="dashboard-panel">
                <header>
                    <p class="dashboard-kicker">30 dias</p>
                    <h3>Afiliaciones</h3>
                </header>
                <div data-chart="affiliations" class="dashboard-chart"><x-ui.skeleton :lines="4" /></div>
            </article>
            <article class="dashboard-panel">
                <header>
                    <p class="dashboard-kicker">30 dias</p>
                    <h3>Recaudacion</h3>
                </header>
                <div data-chart="revenue" class="dashboard-chart"><x-ui.skeleton :lines="4" /></div>
            </article>
            <article class="dashboard-panel">
                <header>
                    <p class="dashboard-kicker">Distribucion</p>
                    <h3>Estados</h3>
                </header>
                <div data-chart="statuses" class="dashboard-chart"><x-ui.skeleton :lines="4" /></div>
            </article>
        </section>

        <div class="dashboard-main-grid">
            <section class="dashboard-panel dashboard-actions-panel" aria-labelledby="quick-actions-title">
                <header>
                    <p class="dashboard-kicker">Accesos</p>
                    <h3 id="quick-actions-title">Acciones rapidas</h3>
                </header>
                <div class="dashboard-actions">
                    @forelse($quickActions as $action)
                        <a class="dashboard-action dashboard-tone-{{ $action['tone'] }}" href="{{ route($action['route']) }}" aria-label="{{ $action['label'] }}">
                            <span><x-ui.icon :name="$action['icon']" class="h-5 w-5" /></span>
                            <strong>{{ $action['label'] }}</strong>
                        </a>
                    @empty
                        <x-ui.empty-state title="Sin acciones disponibles" icon="inbox" />
                    @endforelse
                </div>
            </section>

            <section class="dashboard-panel" aria-labelledby="activity-title">
                <header class="dashboard-panel-header">
                    <span>
                        <p class="dashboard-kicker">Sistema</p>
                        <h3 id="activity-title">Actividad reciente</h3>
                    </span>
                    @if($canAudit)
                        <a class="dashboard-panel-link" href="{{ route('administration.audit.index') }}">Ver actividad</a>
                    @endif
                </header>
                <div class="dashboard-activity">
                    @forelse($recentActivity as $event)
                        <article>
                            <span class="dashboard-activity-icon dashboard-badge-{{ $event['tone'] }}">
                                <x-ui.icon :name="$event['icon']" class="h-4 w-4" />
                            </span>
                            <div>
                                <strong>{{ $event['label'] }}</strong>
                                <p>{{ $event['description'] }}</p>
                            </div>
                            <time>{{ $event['time'] }}</time>
                        </article>
                    @empty
                        <x-ui.empty-state title="No hay actividad reciente" icon="inbox" />
                    @endforelse
                </div>
            </section>
        </div>

        <section class="dashboard-system" aria-labelledby="system-title">
            <div class="dashboard-system-heading">
                <p class="dashboard-kicker">Estado del sistema</p>
                <h3 id="system-title">Servicios principales</h3>
            </div>
            <div class="dashboard-system-items">
                @foreach($healthItems as $item)
                    <span class="dashboard-system-pill dashboard-badge-{{ $item['tone'] }}">
                        <span aria-hidden="true"></span>{{ $item['label'] }} · {{ $item['status'] }}
                    </span>
                @endforeach
            </div>
            <small>Ultima comprobacion: {{ $healthCheckedAt->diffForHumans() }}</small>
        </section>
    </div>
</x-layouts.app>
