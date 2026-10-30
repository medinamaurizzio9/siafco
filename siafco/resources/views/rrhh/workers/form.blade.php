<x-layouts.app :title="$worker->exists ? 'Editar trabajador' : 'Nuevo trabajador'">
@php($editing=$worker->exists)
@php($value=fn($name,$fallback=null)=>old($name,data_get($worker,$name,$fallback)))
<div class="mx-auto max-w-5xl space-y-5">
    <div><h1 class="text-2xl font-black text-siafco-primary-900">{{ $editing?'Editar trabajador':'Nuevo trabajador' }}</h1><p class="text-slate-600">Registra la información personal, laboral y de remuneración.</p></div>
    <form method="post" action="{{ $editing?route('rrhh.workers.update',$worker):route('rrhh.workers.store') }}" class="space-y-5" data-worker-form>@csrf @if($editing)@method('PATCH')@endif
        <x-ui.card>
            <div class="mb-4 border-b border-slate-200 pb-3"><h2 class="text-lg font-black text-siafco-primary-900">Información personal</h2><p class="text-sm text-slate-500">Datos de identificación y contacto del trabajador.</p></div>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="form-label">Tipo de trabajador *</span><select class="form-control" name="type" data-worker-type><option value="scheduled" @selected($value('type')==='scheduled')>Con horario</option><option value="virtual" @selected($value('type')==='virtual')>Virtual</option></select>@error('type')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">Nombre completo *</span><input class="form-control" name="full_name" value="{{ $value('full_name') }}" required>@error('full_name')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">CI *</span><input class="form-control" name="ci" value="{{ $value('ci') }}" required>@error('ci')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">Teléfono *</span><input class="form-control" name="phone" value="{{ $value('phone') }}" required>@error('phone')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="md:col-span-2"><span class="form-label">Correo electrónico</span><input class="form-control" type="email" name="email" value="{{ $value('email') }}">@error('email')<span class="form-error">{{ $message }}</span>@enderror</label>
            </div>
        </x-ui.card>
        <x-ui.card>
            <div class="mb-4 border-b border-slate-200 pb-3"><h2 class="text-lg font-black text-siafco-primary-900">Información laboral</h2><p class="text-sm text-slate-500">Condiciones de incorporación y estado actual.</p></div>
            <div class="grid gap-4 md:grid-cols-2">
                <label><span class="form-label">Cargo</span><input class="form-control" name="position" value="{{ $value('position') }}">@error('position')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">Fecha de ingreso *</span><input class="form-control" type="date" name="joined_on" value="{{ old('joined_on',$worker->joined_on?->format('Y-m-d')) }}" required>@error('joined_on')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label data-scheduled-field><span class="form-label">Horario *</span><input class="form-control" name="schedule_description" value="{{ $value('schedule_description') }}">@error('schedule_description')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label class="flex min-h-12 items-center gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3"><input type="hidden" name="is_active" value="0"><input class="size-4 accent-siafco-secondary-500" type="checkbox" name="is_active" value="1" @checked(old('is_active',$worker->is_active??true))><span class="font-semibold text-slate-700">Trabajador activo</span></label>
            </div>
        </x-ui.card>
        <x-ui.card>
            <div class="mb-4 border-b border-slate-200 pb-3"><h2 class="text-lg font-black text-siafco-primary-900">Remuneración</h2><p class="text-sm text-slate-500">La modalidad fija se oculta para trabajadores virtuales.</p></div>
            <div class="grid gap-4 md:grid-cols-2" data-scheduled-fields>
                <label><span class="form-label">Modalidad de pago *</span><select class="form-control" name="compensation_mode"><option value="monthly" @selected($value('compensation_mode')==='monthly')>Sueldo mensual</option><option value="daily" @selected($value('compensation_mode')==='daily')>Jornal diario</option></select>@error('compensation_mode')<span class="form-error">{{ $message }}</span>@enderror</label>
                <label><span class="form-label">Importe acordado (BOB) *</span><input class="form-control" type="number" min="0.01" step="0.01" name="agreed_amount" value="{{ $value('agreed_amount') }}">@error('agreed_amount')<span class="form-error">{{ $message }}</span>@enderror</label>
            </div>
            <label class="mt-4 flex min-h-12 items-center gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3"><input type="hidden" name="commissions_enabled" value="0"><input class="size-4 accent-siafco-secondary-500" type="checkbox" name="commissions_enabled" value="1" @checked(old('commissions_enabled',$worker->commissions_enabled))><span class="font-semibold text-slate-700">Comisiones habilitadas</span></label>
        </x-ui.card>
        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><x-ui.button href="{{ route('rrhh.workers.index') }}" variant="outline">Cancelar</x-ui.button><x-ui.button type="submit">{{ $editing?'Guardar cambios':'Registrar trabajador' }}</x-ui.button></div>
    </form>
</div>
@push('scripts')<script>document.addEventListener('DOMContentLoaded',()=>{const type=document.querySelector('[data-worker-type]');const compensation=document.querySelector('[data-scheduled-fields]');const schedule=document.querySelector('[data-scheduled-field]');const sync=()=>{const virtual=type.value==='virtual';compensation.hidden=virtual;schedule.hidden=virtual;if(virtual){compensation.querySelectorAll('input').forEach(input=>input.value='');schedule.querySelectorAll('input').forEach(input=>input.value='');}};type.addEventListener('change',sync);sync();});</script>@endpush
</x-layouts.app>
