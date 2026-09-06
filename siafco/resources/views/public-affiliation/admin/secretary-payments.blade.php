<x-layouts.app title="Pago en secretaría">
    <section class="section-card">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-black text-siafco-primary-900">Pago en secretaría</h1>
                <p class="mt-1 text-sm text-slate-600">Busca a una persona previamente registrada para cargar su pago de afiliación.</p>
            </div>
            @if(auth()->user()->hasRole(['superadministrador', 'administrador', 'gerente', 'caja', 'cajero']))
                <a class="btn-secondary" href="{{ route('affiliates.office.create') }}">Nueva afiliación en oficina</a>
            @endif
        </div>

        <form class="mt-6 flex flex-col gap-3 sm:flex-row" method="get">
            <label class="min-w-0 flex-1">
                <span class="sr-only">Buscar persona registrada</span>
                <input class="form-input min-h-12" name="search" value="{{ $search }}" maxlength="100" placeholder="Buscar por CI, nombre, teléfono o código de solicitud" autofocus>
            </label>
            <button class="btn-primary min-h-12" type="submit">Buscar</button>
        </form>
    </section>

    @if($search !== '')
        <section class="mt-5 overflow-hidden rounded-lg border border-slate-200 bg-white">
            @if($applications->isEmpty())
                <div class="p-6 text-center">
                    <p class="font-bold text-slate-800">No se encontró un registro previo para esta persona.</p>
                    @if(auth()->user()->hasRole(['superadministrador', 'administrador', 'gerente', 'caja', 'cajero']))
                        <a class="btn-secondary mt-4" href="{{ route('affiliates.office.create') }}">Nueva afiliación en oficina</a>
                    @endif
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Nombre</th><th>CI</th><th>Sector</th><th>Plan</th><th>Estado</th><th>Pago</th><th>Acción</th></tr></thead>
                        <tbody>
                        @foreach($applications as $application)
                            @php
                                $payment = $application->payment;
                            @endphp
                            <tr>
                                <td class="font-bold">{{ $application->person?->full_name ?? $application->affiliate?->full_name }}</td>
                                <td>{{ $application->person?->ci ?? $application->affiliate?->ci }}</td>
                                <td>{{ $application->sector?->name ?? 'Sin sector' }}</td>
                                <td>{{ $application->plan?->name ?? 'Sin plan' }}</td>
                                <td><x-affiliation-status :status="$application->display_status" size="sm" /></td>
                                <td>
                                    @if($payment)<x-payment-status :status="$payment->status" size="sm" />@else<span class="font-bold text-amber-700">Sin pago</span>@endif
                                </td>
                                <td>
                                    @if($application->can_load_payment)
                                        <a class="btn-primary" href="{{ route('public-affiliation.admin.show', ['application' => $application, 'cargar_pago' => 1]) }}">Cargar pago</a>
                                    @else
                                        <a class="btn-secondary" href="{{ route('public-affiliation.admin.show', $application) }}">Ver registro</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
</x-layouts.app>
