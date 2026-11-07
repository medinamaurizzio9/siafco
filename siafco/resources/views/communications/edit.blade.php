<x-layouts.app :title="$item->exists ? 'Editar comunicado' : 'Crear comunicado'">
    @php
        $restoring = session()->hasOldInput();
        $selectedSectors = old('sectors', $restoring ? [] : ($item->audience['sectors'] ?? []));
    @endphp
    <form class="mx-auto grid w-full max-w-5xl gap-4" enctype="multipart/form-data" method="post"
          action="{{ $item->exists ? route('communications.update', $item) : route('communications.store') }}">
        @csrf
        @if($item->exists) @method('PUT') @endif

        <section class="section-card grid min-w-0 gap-4" aria-labelledby="content-heading">
            <h2 id="content-heading" class="text-lg font-bold">Contenido del comunicado</h2>
            <label class="block min-w-0"><span class="form-label">Título</span><input class="form-input" name="title" aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" required maxlength="200" value="{{ old('title', $item->title) }}"></label>
            <label class="block min-w-0"><span class="form-label">Resumen</span><textarea class="form-input" name="summary" aria-invalid="{{ $errors->has('summary') ? 'true' : 'false' }}" required rows="2" maxlength="500">{{ old('summary', $item->summary) }}</textarea></label>
            <div class="min-w-0">
                <label class="form-label" for="communication-body">Contenido completo</label>
                <div class="rounded-lg border p-3">
                    <div class="mb-2 flex flex-wrap gap-2" role="group" aria-label="Formato del contenido">
                        @foreach(['strong'=>'Negrita', 'em'=>'Cursiva', 'p'=>'Párrafo', 'ul'=>'Lista', 'li'=>'Elemento de lista'] as $tag=>$label)
                            <button type="button" class="btn-secondary" data-format="{{ $tag }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <textarea class="form-input" id="communication-body" name="body" required rows="8" maxlength="60000" aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}">{{ old('body', html_entity_decode($item->body ?? '', ENT_QUOTES, 'UTF-8')) }}</textarea>
                    <p class="form-helper">Selecciona texto y usa los botones para insertar etiquetas de formato.</p>
                </div>
            </div>
        </section>

        <section class="section-card grid min-w-0 gap-4 sm:grid-cols-2" aria-labelledby="options-heading">
            <h2 id="options-heading" class="text-lg font-bold sm:col-span-2">Opciones y archivos</h2>
            <label class="block min-w-0"><span class="form-label">Prioridad</span><select class="form-input" name="priority" aria-invalid="{{ $errors->has('priority') ? 'true' : 'false' }}">
                @foreach(['normal','important','urgent'] as $priority)
                    <option value="{{ $priority }}" @selected(old('priority', $item->priority ?? 'normal') === $priority)>{{ __('communications.'.$priority, [], 'es') }}</option>
                @endforeach
            </select></label>
            <label class="block min-w-0"><span class="form-label">Vencimiento (opcional)</span><input class="form-input" name="expires_at" aria-invalid="{{ $errors->has('expires_at') ? 'true' : 'false' }}" type="datetime-local" value="{{ old('expires_at', $item->expires_at?->format('Y-m-d\TH:i')) }}"></label>
            <label class="flex items-center gap-2 sm:col-span-2">
                <input type="checkbox" name="requires_confirmation" value="1" @checked(old('requires_confirmation', !$restoring && $item->requires_confirmation))> Solicitar confirmación de lectura
            </label>
            <label class="block min-w-0"><span class="form-label">Imagen de portada</span><input class="form-input" name="cover" aria-invalid="{{ $errors->has('cover') ? 'true' : 'false' }}" type="file" accept=".png,.jpg,.jpeg,.webp"><span class="form-helper">JPG, PNG o WebP · hasta 4 MB</span></label>
            <label class="block min-w-0"><span class="form-label">Archivos adjuntos</span><input class="form-input" type="file" multiple name="files[]" accept=".png,.jpg,.jpeg,.webp,.pdf" aria-invalid="{{ $errors->has('files') || $errors->has('files.*') ? 'true' : 'false' }}"><span class="form-helper">Hasta 8 imágenes o PDF de 8 MB cada uno</span></label>
            @if($errors->any())<p class="form-helper sm:col-span-2">Si habías adjuntado archivos, vuelve a seleccionarlos.</p>@endif
        </section>

        <section class="section-card grid min-w-0 gap-4" aria-labelledby="recipient-heading" id="candidate-endpoint"
                 data-url="{{ route('communications.candidates') }}" data-count-url="{{ route('communications.audience-count') }}">
            <h2 id="recipient-heading" class="text-lg font-bold">Destinatarios</h2>
            <label class="flex items-center gap-2"><input type="checkbox" name="all" value="1" @checked(old('all', !$restoring && ($item->audience['all'] ?? false)))> Todos los afiliados elegibles</label>
            <fieldset class="min-w-0 rounded-lg border p-3">
                <legend class="form-label">Sectores completos</legend>
                <div class="grid max-h-56 gap-2 overflow-y-auto sm:grid-cols-2">
                    @forelse($sectors as $sector)
                        <label class="flex items-center gap-2"><input type="checkbox" name="sectors[]" value="{{ $sector->id }}" @checked(in_array($sector->id, (array) $selectedSectors))> {{ $sector->name }}</label>
                    @empty <p>Sin sectores disponibles.</p> @endforelse
                </div>
            </fieldset>
            <fieldset class="grid min-w-0 gap-3">
                <legend class="form-label">Selección individual</legend>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="sm:col-span-2"><span class="form-label">Nombre, CI o código de afiliado</span><input type="search" class="form-input" id="candidate-q"></label>
                    <label><span class="form-label">Filtrar por sector</span><select class="form-input" id="candidate-sector"><option value="">Todos los sectores</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}">{{ $sector->name }}</option>@endforeach</select></label>
                    <label><span class="form-label">Filtrar por estado</span><input class="form-input" id="candidate-status" placeholder="Ej. activo"></label>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button class="btn-secondary" type="button" id="find">Buscar</button>
                    <button class="btn-secondary" type="button" id="bulk">Seleccionar todos los resultados filtrados</button>
                    <button class="btn-secondary" type="button" id="clear-selection">Eliminar selección individual</button>
                </div>
                <input type="hidden" name="affiliate_ids" id="selected-ids" value="{{ old('affiliate_ids', json_encode($item->audience['affiliates'] ?? [])) }}">
                <output class="text-sm font-bold" id="selected-count" aria-live="polite"></output>
                <p id="search-status" role="status" class="form-helper"></p>
                <div id="candidate-results" class="grid max-h-56 gap-2 overflow-y-auto"></div>
                <div><button class="btn-secondary" type="button" id="more" hidden>Más resultados</button></div>
            </fieldset>
            <output class="rounded-lg border p-3 font-bold" id="audience-total" aria-live="polite">Calculando destinatarios únicos…</output>
        </section>
        <div class="flex flex-wrap gap-2">
            <button class="btn-primary">Guardar borrador y revisar destinatarios</button>
            <a class="btn-secondary" href="{{ route('communications.index') }}">Volver al listado</a>
        </div>
    </form>
    <script src="{{ asset('js/communication-audience.js') }}" defer></script>
</x-layouts.app>
