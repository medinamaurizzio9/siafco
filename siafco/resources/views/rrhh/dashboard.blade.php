<x-layouts.app title="Personal y Comisiones">
<div class="space-y-6">
    <div><p class="text-sm font-bold uppercase text-siafco-gold-600">Personal y Comisiones</p><h1 class="text-2xl font-black text-siafco-primary-900">Resumen</h1><p class="text-slate-600">Indicadores reales de personal, ventas y comisiones confirmadas.</p></div>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach(['Trabajadores activos'=>$metrics['active_workers'],'Trabajadores virtuales'=>$metrics['virtual_workers'],'Productos activos'=>$metrics['active_products'],'Ventas pendientes'=>$metrics['pending_sales'],'Ventas confirmadas'=>$metrics['confirmed_sales']] as $label=>$value)
            <x-ui.card><p class="text-sm font-bold text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-black text-siafco-primary-900">{{ $value }}</p></x-ui.card>
        @endforeach
        <x-ui.card><p class="text-sm font-bold text-slate-500">Importe vendido confirmado</p><p class="mt-2 text-2xl font-black text-siafco-primary-900">BOB {{ number_format((float)$metrics['confirmed_amount'],2) }}</p></x-ui.card>
        <x-ui.card><p class="text-sm font-bold text-slate-500">Comisiones confirmadas</p><p class="mt-2 text-2xl font-black text-siafco-primary-900">BOB {{ number_format((float)$metrics['confirmed_commissions'],2) }}</p></x-ui.card>
    </div>
    <div class="flex flex-wrap gap-3">@if(auth()->user()->hasPermission('rrhh.workers.view'))<x-ui.button href="{{ route('rrhh.workers.index') }}" icon="users">Ver personal</x-ui.button>@endif @if(auth()->user()->hasPermission('rrhh.products.view'))<x-ui.button href="{{ route('rrhh.products.index') }}" variant="secondary">Ver productos</x-ui.button>@endif @if(auth()->user()->hasPermission('rrhh.sales.view'))<x-ui.button href="{{ route('rrhh.sales.index') }}" variant="outline">Ver ventas</x-ui.button>@endif @if(auth()->user()->hasPermission('rrhh.reports.view'))<x-ui.button href="{{ route('rrhh.reports.index') }}" variant="outline">Ver informes</x-ui.button>@endif</div>
</div>
</x-layouts.app>
