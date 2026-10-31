<x-layouts.app title="Detalle de contrato">
<div class="space-y-5">
    <div class="flex flex-wrap justify-between gap-3">
        <div><p class="text-sm font-bold text-siafco-gold-600">{{ $contract->contract_number }}</p><h1 class="text-2xl font-black text-siafco-primary-900">{{ $contract->investor->full_name }}</h1></div>
        <x-ui.button href="{{ route('simple-investments.contracts.print', $contract) }}" variant="outline" target="_blank">Resumen imprimible</x-ui.button>
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <h2 class="text-lg font-black">Datos del contrato</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <p><span class="form-label">Producto</span>{{ $contract->product_name_snapshot }}</p><p><span class="form-label">Estado</span>{{ str($contract->status)->replace('_', ' ')->headline() }}</p>
                <p><span class="form-label">Cantidad</span>{{ $contract->quantity }}</p><p><span class="form-label">Precio unitario histórico</span>{{ $contract->currency }} {{ number_format((float) $contract->unit_price_snapshot, 2) }}</p>
                <p><span class="form-label">Total de registro</span>{{ $contract->currency }} {{ number_format((float) $contract->total_amount, 2) }}</p><p><span class="form-label">Fecha</span>{{ $contract->registered_on->format('d/m/Y') }}</p>
            </div>
            <p class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm font-bold text-amber-900">Este registro no acredita un pago ni genera recibo.</p>
        </x-ui.card>
        <x-ui.card><h2 class="text-lg font-black">Acciones emitidas</h2><div class="mt-3 flex flex-wrap gap-2">@forelse($contract->units as $unit)<x-ui.badge variant="success">{{ $unit->code }}</x-ui.badge>@empty<p class="text-sm text-slate-500">Se generan únicamente al firmar.</p>@endforelse</div></x-ui.card>
    </div>

    <x-ui.card>
        @php
            $currentDocument = $contract->currentDocument;
            $documentLabels = ['uploaded' => 'Documento cargado', 'verified' => 'Verificado', 'observed' => 'Observado'];
        @endphp
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><p class="text-xs font-bold uppercase text-siafco-gold-600">Documentación del contrato</p><h2 class="text-lg font-black text-siafco-primary-900">Documento firmado</h2></div>
            <x-ui.badge variant="{{ $currentDocument?->status === 'verified' ? 'success' : ($currentDocument?->status === 'observed' ? 'danger' : 'warning') }}">{{ $currentDocument ? $documentLabels[$currentDocument->status] : 'Pendiente de documento' }}</x-ui.badge>
        </div>
        @if($currentDocument)
            <div class="mt-4 grid gap-4 sm:grid-cols-3"><p><span class="form-label">Versión vigente</span>v{{ $currentDocument->version }}</p><p><span class="form-label">Fecha de carga</span>{{ $currentDocument->uploaded_at->format('d/m/Y H:i') }}</p><p><span class="form-label">Responsable</span>{{ $currentDocument->uploader?->name ?? 'Usuario no disponible' }}</p></div>
            @if($currentDocument->review_notes)<p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><b>Observación:</b> {{ $currentDocument->review_notes }}</p>@endif
        @endif
        @if(auth()->user()->hasPermission('simple_investments.documents.upload'))
            <form class="mt-5 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end" method="post" enctype="multipart/form-data" action="{{ route('simple-investments.contracts.documents.store', $contract) }}">@csrf
                <label><span class="form-label">{{ $currentDocument ? 'Cargar nueva versión' : 'Subir documento firmado' }}</span><input class="form-control" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.webp" required><span class="text-xs text-slate-500">PDF, JPG, PNG o WEBP. Máximo {{ number_format(config('simple_investments.document_max_kb') / 1024, 0) }} MB.</span></label>
                <x-ui.button type="submit">{{ $currentDocument ? 'Sustituir documento' : 'Subir documento' }}</x-ui.button>
            </form>
        @endif
        @if($errors->has('document') || $errors->has('review_notes'))<p class="mt-3 form-error">{{ $errors->first('document') ?: $errors->first('review_notes') }}</p>@endif
        @if($currentDocument && auth()->user()->hasPermission('simple_investments.documents.verify'))
            <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-200 pt-5">
                <form method="post" action="{{ route('simple-investments.contracts.documents.verify', [$contract, $currentDocument]) }}">@csrf<x-ui.button type="submit">Verificar documento</x-ui.button></form>
                <form class="flex flex-1 flex-wrap gap-2" method="post" action="{{ route('simple-investments.contracts.documents.observe', [$contract, $currentDocument]) }}">@csrf<input class="form-control min-w-64 flex-1" name="review_notes" placeholder="Motivo de observación" required><x-ui.button type="submit" variant="outline">Observar</x-ui.button></form>
            </div>
        @endif
        <div class="mt-6 border-t border-slate-200 pt-5">
            <h3 class="font-black text-siafco-primary-900">Historial de versiones</h3>
            <div class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-100 text-left text-xs uppercase"><tr><th class="p-3">Versión</th><th class="p-3">Archivo</th><th class="p-3">Carga</th><th class="p-3">Estado</th><th class="p-3">Acciones</th></tr></thead><tbody class="divide-y">
                @forelse($contract->documentVersions as $document)
                    <tr><td class="p-3 font-bold">v{{ $document->version }}</td><td class="p-3">{{ $document->original_name }}</td><td class="p-3">{{ $document->uploaded_at->format('d/m/Y H:i') }}<br><span class="text-xs text-slate-500">{{ $document->uploader?->name }}</span></td><td class="p-3">{{ $documentLabels[$document->status] }}</td><td class="p-3"><div class="flex gap-2">@if(auth()->user()->hasPermission('simple_investments.documents.view'))<x-ui.button href="{{ route('simple-investments.contracts.documents.view', [$contract, $document]) }}" variant="outline" target="_blank">Ver</x-ui.button><x-ui.button href="{{ route('simple-investments.contracts.documents.download', [$contract, $document]) }}" variant="outline">Descargar</x-ui.button>@endif</div></td></tr>
                @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Aún no se cargó documentación.</td></tr>@endforelse
            </tbody></table></div>
        </div>
    </x-ui.card>

    <x-ui.card>
        <h2 class="text-lg font-black text-siafco-primary-900">Próximas fechas programadas</h2><p class="mt-1 text-sm text-slate-600">Proyección limitada basada en los parámetros conservados al firmar. No genera importes, rendimientos ni obligaciones de pago.</p>
        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-slate-100 text-left text-xs uppercase"><tr><th class="p-3">N.º</th><th class="p-3">Fecha</th><th class="p-3">Días</th><th class="p-3">Estado</th><th class="p-3">Aviso</th></tr></thead><tbody class="divide-y">
            @forelse($schedule as $item)<tr><td class="p-3">{{ $item['index'] }}</td><td class="p-3 font-bold">{{ $item['date']->format('d/m/Y') }}</td><td class="p-3">{{ $item['days_remaining'] }}</td><td class="p-3">{{ $item['state'] }}</td><td class="p-3">{{ $item['notice_status'] }}</td></tr>@empty<tr><td colspan="5" class="p-6 text-center text-slate-500">El calendario se habilita cuando el contrato queda firmado.</td></tr>@endforelse
        </tbody></table></div>
    </x-ui.card>

    @if(auth()->user()->hasPermission('simple_investments.contracts.transition'))
        <x-ui.card><h2 class="text-lg font-black">Cambiar estado</h2><div class="mt-4 flex flex-wrap gap-3">
            @if($contract->status === 'draft')<form method="post" action="{{ route('simple-investments.contracts.pending', $contract) }}">@csrf<x-ui.button type="submit">Enviar a firma</x-ui.button></form>@endif
            @if($contract->status === 'pending_signature')<form method="post" action="{{ route('simple-investments.contracts.sign', $contract) }}" class="flex flex-wrap gap-2">@csrf<input class="form-control w-auto" type="date" name="signed_on" value="{{ today()->format('Y-m-d') }}" required><x-ui.button type="submit">Registrar firma y emitir acciones</x-ui.button></form>@endif
            @if($contract->status !== 'void')<form method="post" action="{{ route('simple-investments.contracts.void', $contract) }}" class="flex flex-wrap gap-2">@csrf<input class="form-control w-auto" name="void_reason" placeholder="Motivo de anulación" required><x-ui.button type="submit" variant="danger">Anular</x-ui.button></form>@endif
        </div></x-ui.card>
    @endif
</div>
</x-layouts.app>
