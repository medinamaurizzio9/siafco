@php
    $user = auth()->user();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'Buenos dias' : ($hour < 19 ? 'Buenas tardes' : 'Buenas noches');
    $currency = 'BOB';
    $systemFindings = class_exists(\App\Services\SiafcoHealthCheckService::class)
        ? app(\App\Services\SiafcoHealthCheckService::class)->findings()
        : [];
    $totalFindings = collect($systemFindings)->sum();
    $pendingBalance = (float) ($metrics['pendingBalance'] ?? 0);
    $confirmedAmount = (float) ($metrics['confirmedAmount'] ?? 0);
    $expectedAmount = $confirmedAmount + $pendingBalance;
    $activeAffiliates = (int) ($metrics['active'] ?? 0);
    $totalAffiliates = (int) ($metrics['affiliates'] ?? 0);
    $inactiveAffiliates = max($totalAffiliates - $activeAffiliates, 0);
    $dashboardCharts = [
        'finance' => [
            'labels' => ['Confirmado', 'Saldo pendiente'],
            'series' => [$confirmedAmount, $pendingBalance],
        ],
        'affiliations' => [
            'labels' => ['Activos', 'Otros estados'],
            'series' => [$activeAffiliates, $inactiveAffiliates],
        ],
        'operations' => [
            'labels' => ['Pagos pendientes', 'Pagos confirmados', 'Credenciales'],
            'series' => [
                (int) ($metrics['pendingPayments'] ?? 0),
                (int) ($metrics['confirmedPayments'] ?? 0),
                (int) ($metrics['credentials'] ?? 0),
            ],
        ],
    ];
    $quickActions = [
        ['label' => 'Nuevo afiliado', 'route' => 'affiliates.create', 'permission' => 'affiliates.create', 'icon' => 'user'],
        ['label' => 'Registrar pago', 'route' => 'payments.create', 'permission' => 'payments.create', 'icon' => 'credit-card'],
        ['label' => 'Tesoreria', 'route' => 'payments.index', 'permission' => 'payments.view', 'icon' => 'chart'],
        ['label' => 'Credenciales', 'route' => 'credentials.index', 'permission' => 'credentials.view', 'icon' => 'briefcase'],
        ['label' => 'Usuarios internos', 'route' => 'admin.users.index', 'permission' => 'users.view', 'icon' => 'user'],
        ['label' => 'Reportes', 'route' => 'reports.index', 'permission' => 'reports.view', 'icon' => 'chart'],
        ['label' => 'Configuracion', 'route' => 'institutional-settings.edit', 'permission' => 'settings.view', 'icon' => 'settings'],
    ];
@endphp

<x-layouts.app title="Centro de operaciones">
    <div class="space-y-6" data-dashboard-charts='@json($dashboardCharts)'>
        <section class="card-summary overflow-hidden">
            <div class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr] xl:items-center">
                <div>
                    <nav class="mb-4 flex items-center gap-2 text-sm font-bold text-siafco-muted" aria-label="Breadcrumb">
                        <span>Inicio</span>
                        <span aria-hidden="true">/</span>
                        <span class="text-siafco-primary-900">Centro de operaciones</span>
                    </nav>
                    <p class="ds-title-eyebrow">{{ now()->translatedFormat('l, d \\d\\e F \\d\\e Y') }}</p>
                    <h2 class="mt-2 text-3xl font-black text-siafco-primary-900 sm:text-4xl">{{ $greeting }}, {{ strtok($user->name, ' ') }}.</h2>
                    <p class="mt-3 max-w-3xl text-base leading-7 text-siafco-muted">
                        Panel ejecutivo para supervisar afiliaciones, tesoreria, credenciales y salud operativa de SIAFCO.
                    </p>
                    <div class="mt-5 grid gap-2 text-sm text-siafco-text sm:grid-cols-2">
                        <p><strong>{{ (int) ($metrics['pendingPayments'] ?? 0) }}</strong> pagos requieren seguimiento.</p>
                        <p><strong>{{ (int) ($metrics['newAffiliates'] ?? 0) }}</strong> registros creados hoy.</p>
                        <p><strong>{{ (int) ($metrics['credentials'] ?? 0) }}</strong> credenciales emitidas.</p>
                        <p><strong>{{ $totalFindings === 0 ? 'OK' : $totalFindings }}</strong> estado general del sistema.</p>
                    </div>
                </div>
                <div class="grid gap-3 rounded-xl border border-siafco-border bg-white/80 p-4">
                    <label class="relative block">
                        <span class="sr-only">Buscador global preparado</span>
                        <x-ui.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-siafco-muted" />
                        <input class="form-input pl-10" placeholder="Buscar afiliados, pagos o credenciales" disabled>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <x-ui.button variant="secondary" icon="bell" disabled>Notificaciones</x-ui.button>
                        <x-ui.button variant="secondary" icon="user" disabled>{{ $user->roleLabel() }}</x-ui.button>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Indicadores principales">
            @foreach([
                ['label' => 'Afiliados activos', 'value' => $metrics['active'] ?? 0, 'description' => 'Cuentas con afiliacion vigente.', 'status' => 'Operativo', 'icon' => 'user', 'route' => 'affiliates.index'],
                ['label' => 'Afiliaciones pendientes', 'value' => 'Sin datos', 'description' => 'Pendiente de exponer por controlador.', 'status' => 'Preparado', 'icon' => 'inbox', 'route' => 'affiliates.index'],
                ['label' => 'Pagos pendientes', 'value' => $metrics['pendingPayments'] ?? 0, 'description' => 'Pagos por revisar o confirmar.', 'status' => 'Atencion', 'icon' => 'credit-card', 'route' => 'payments.index'],
                ['label' => 'Pagos de hoy', 'value' => $metrics['todayPayments'] ?? 0, 'description' => 'Dato disponible: pagos registrados hoy.', 'status' => 'Diario', 'icon' => 'check', 'route' => 'payments.index'],
                ['label' => 'Credenciales emitidas', 'value' => $metrics['credentials'] ?? 0, 'description' => 'Credenciales digitales generadas.', 'status' => 'Actualizado', 'icon' => 'briefcase', 'route' => 'credentials.index'],
                ['label' => 'Usuarios internos', 'value' => 'Sin datos', 'description' => 'Preparado para metrica de usuarios.', 'status' => 'Preparado', 'icon' => 'user', 'route' => 'admin.users.index'],
                ['label' => 'Recaudacion', 'value' => $currency.' '.number_format($confirmedAmount, 2), 'description' => 'Monto confirmado acumulado.', 'status' => 'Finanzas', 'icon' => 'chart', 'route' => 'reports.index'],
            ] as $kpi)
                <article class="card-action group" title="{{ $kpi['description'] }}">
                    <div class="flex items-start justify-between gap-4">
                        <span class="grid h-11 w-11 place-items-center rounded-lg bg-siafco-primary-50 text-siafco-primary-900">
                            <x-ui.icon :name="$kpi['icon']" class="h-5 w-5" />
                        </span>
                        <x-ui.badge variant="{{ $kpi['status'] === 'Atencion' ? 'warning' : 'info' }}">{{ $kpi['status'] }}</x-ui.badge>
                    </div>
                    <p class="mt-4 text-sm font-bold text-siafco-muted">{{ $kpi['label'] }}</p>
                    <strong class="mt-1 block text-3xl font-black text-siafco-primary-900">{{ $kpi['value'] }}</strong>
                    <p class="mt-2 min-h-10 text-sm leading-5 text-siafco-muted">{{ $kpi['description'] }}</p>
                    @if(Route::has($kpi['route']))
                        <a class="mt-4 inline-flex text-sm font-black text-siafco-primary-900 underline decoration-siafco-gold-500/60 underline-offset-4" href="{{ route($kpi['route']) }}">Abrir</a>
                    @endif
                </article>
            @endforeach
        </section>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <div class="space-y-6">
                <section class="grid gap-6 lg:grid-cols-2">
                    <x-ui.card title="Centro de alertas" eyebrow="Operacion">
                        <div class="grid gap-3">
                            @foreach([
                                ['label' => 'Solicitudes pendientes', 'value' => 'Sin datos', 'variant' => 'muted'],
                                ['label' => 'Pagos pendientes', 'value' => $metrics['pendingPayments'] ?? 0, 'variant' => (($metrics['pendingPayments'] ?? 0) > 0 ? 'warning' : 'success')],
                                ['label' => 'Credenciales suspendidas', 'value' => 'Sin datos', 'variant' => 'muted'],
                                ['label' => 'Usuarios bloqueados', 'value' => 'Sin datos', 'variant' => 'muted'],
                                ['label' => 'Hallazgos HealthCheck', 'value' => $totalFindings, 'variant' => $totalFindings > 0 ? 'warning' : 'success'],
                            ] as $alert)
                                <div class="flex items-center justify-between rounded-lg border border-siafco-border bg-slate-50 px-3 py-2">
                                    <span class="font-bold text-siafco-text">{{ $alert['label'] }}</span>
                                    <x-ui.badge :variant="$alert['variant']">{{ $alert['value'] }}</x-ui.badge>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>

                    <x-ui.card title="Resumen financiero" eyebrow="Tesoreria">
                        <dl class="grid gap-3">
                            @foreach([
                                'Monto esperado' => $currency.' '.number_format($expectedAmount, 2),
                                'Monto confirmado' => $currency.' '.number_format($confirmedAmount, 2),
                                'Saldo pendiente' => $currency.' '.number_format($pendingBalance, 2),
                                'Cantidad de pagos' => $metrics['confirmedPayments'] ?? 0,
                            ] as $label => $value)
                                <div class="flex items-center justify-between border-b border-siafco-border pb-2 last:border-0 last:pb-0">
                                    <dt class="text-sm font-bold text-siafco-muted">{{ $label }}</dt>
                                    <dd class="font-black text-siafco-primary-900">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </x-ui.card>
                </section>

                <section class="grid gap-6 lg:grid-cols-2">
                    <x-ui.card title="Actividad reciente" eyebrow="Timeline">
                        <div class="grid gap-3">
                            @forelse($recentPayments as $payment)
                                <article class="flex gap-3 rounded-lg border border-siafco-border p-3">
                                    <span class="mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-siafco-gold-50 text-siafco-primary-900">
                                        <x-ui.icon name="credit-card" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-black text-siafco-primary-900">Pago {{ \App\Support\PaymentStatus::label($payment->status) }}</p>
                                        <p class="truncate text-sm text-siafco-muted">{{ $payment->affiliate?->full_name ?? 'Afiliado no disponible' }}</p>
                                        <p class="text-xs font-bold text-siafco-muted">{{ $payment->created_at?->format('d/m/Y H:i') }}</p>
                                    </div>
                                </article>
                            @empty
                                <x-ui.empty-state title="Sin actividad reciente" message="Los eventos apareceran cuando existan registros auditados." icon="inbox" />
                            @endforelse
                        </div>
                    </x-ui.card>

                    <x-ui.card title="Resumen de afiliaciones" eyebrow="Estados">
                        <div class="grid gap-3">
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Activos</span><x-ui.badge variant="success">{{ $activeAffiliates }}</x-ui.badge></div>
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Pendientes</span><x-ui.badge variant="muted">Sin datos</x-ui.badge></div>
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Suspendidos</span><x-ui.badge variant="muted">Sin datos</x-ui.badge></div>
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2"><span>Baja</span><x-ui.badge variant="muted">Sin datos</x-ui.badge></div>
                        </div>
                    </x-ui.card>
                </section>

                <x-ui.card title="Analitica institucional" eyebrow="Graficos">
                    <div class="grid gap-4 lg:grid-cols-3">
                        <div class="rounded-lg border border-siafco-border p-3">
                            <p class="mb-2 text-sm font-black text-siafco-primary-900">Afiliaciones</p>
                            <div data-chart="affiliations" class="min-h-60"><x-ui.skeleton :lines="4" /></div>
                        </div>
                        <div class="rounded-lg border border-siafco-border p-3">
                            <p class="mb-2 text-sm font-black text-siafco-primary-900">Finanzas</p>
                            <div data-chart="finance" class="min-h-60"><x-ui.skeleton :lines="4" /></div>
                        </div>
                        <div class="rounded-lg border border-siafco-border p-3">
                            <p class="mb-2 text-sm font-black text-siafco-primary-900">Operacion</p>
                            <div data-chart="operations" class="min-h-60"><x-ui.skeleton :lines="4" /></div>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <aside class="space-y-6">
                <x-ui.card title="Accesos rapidos" eyebrow="Acciones">
                    <div class="grid gap-2">
                        @foreach($quickActions as $action)
                            @if($user->hasPermission($action['permission']) && Route::has($action['route']))
                                <x-ui.button variant="secondary" :icon="$action['icon']" :href="route($action['route'])" class="justify-start">
                                    {{ $action['label'] }}
                                </x-ui.button>
                            @else
                                <x-ui.button variant="ghost" :icon="$action['icon']" disabled class="justify-start">
                                    {{ $action['label'] }}
                                </x-ui.button>
                            @endif
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card title="Estado del sistema" eyebrow="Salud">
                    <div class="grid gap-3">
                        @foreach([
                            'Base de datos' => 'Sin datos',
                            'Storage' => 'Sin datos',
                            'API movil' => 'Sin datos',
                            'Credenciales' => $totalFindings > 0 ? 'Advertencia' : 'OK',
                            'Cola' => 'Sin datos',
                            'Cache' => 'Sin datos',
                        ] as $label => $status)
                            <div class="flex items-center justify-between rounded-lg border border-siafco-border px-3 py-2">
                                <span class="text-sm font-bold text-siafco-text">{{ $label }}</span>
                                <x-ui.badge variant="{{ $status === 'OK' ? 'success' : ($status === 'Advertencia' ? 'warning' : 'muted') }}">{{ $status }}</x-ui.badge>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                <x-ui.card title="Pagos recientes" eyebrow="Tesoreria">
                    @if($recentPayments->isEmpty())
                        <x-ui.empty-state title="Sin pagos registrados" icon="credit-card" />
                    @else
                        <div class="grid gap-3">
                            @foreach($recentPayments->take(4) as $payment)
                                <a class="rounded-lg border border-siafco-border p-3 transition hover:bg-slate-50" href="{{ route('payments.show', $payment) }}">
                                    <p class="truncate font-black text-siafco-primary-900">{{ $payment->affiliate?->full_name ?? 'Afiliado no disponible' }}</p>
                                    <p class="text-sm text-siafco-muted">{{ $payment->currency ?? 'BOB' }} {{ number_format((float) ($payment->paid_amount ?? $payment->amount), 2) }}</p>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>
            </aside>
        </div>
    </div>
</x-layouts.app>
