@props([
    'name' => 'photo',
    'required' => true,
    'initialSrc' => null,
    'label' => 'Fotografía para credencial',
    'description' => null,
    'selectLabel' => 'Seleccionar fotografía',
    'cancelLabel' => 'Eliminar',
])

<div class="sm:col-span-2" data-photo-cropper data-photo-required="{{ $required ? 'true' : 'false' }}"
    data-photo-initial="{{ $initialSrc }}" data-field-wrapper>
    <span class="form-label" data-field-label>
        {{ $label }} @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif
    </span>
    @if($description)<p class="mb-3 text-sm text-slate-600">{{ $description }}</p>@endif
    <div class="rounded-lg border border-slate-300 bg-slate-50 p-4 @error($name) border-red-500 bg-red-50 @enderror" data-photo-box>
        <input id="{{ $name }}-source" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" data-photo-source>
        <input id="{{ $name }}" class="sr-only" type="file" name="{{ $name }}" accept="image/jpeg" data-photo-output
            aria-required="{{ $required ? 'true' : 'false' }}" aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            aria-describedby="{{ $name }}-error {{ $name }}-status" data-validate-field data-touched="{{ $errors->has($name) ? 'true' : 'false' }}">

        <div class="grid gap-5 sm:grid-cols-[180px_1fr] sm:items-center">
            <div class="aspect-square w-full max-w-[180px] overflow-hidden rounded border-2 border-[#d4af37] bg-white shadow-sm" data-photo-preview>
                <div class="{{ $initialSrc ? 'hidden' : 'grid' }} h-full place-items-center p-4 text-center text-sm text-slate-500" data-photo-placeholder>Selecciona una fotografía</div>
                <img class="{{ $initialSrc ? '' : 'hidden' }} h-full w-full object-cover" src="{{ $initialSrc }}" data-photo-preview-image alt="Vista previa de fotografía institucional">
            </div>
            <div>
                <p id="{{ $name }}-status" class="text-sm text-slate-600" data-photo-status>JPG, JPEG, PNG o WEBP. Tamaño máximo: 5 MB.</p>
                <p class="mt-2 hidden text-sm font-bold text-emerald-800" data-photo-details></p>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <label for="{{ $name }}-source" class="btn-primary cursor-pointer" data-photo-select>{{ $selectLabel }}</label>
                    <button class="btn-secondary hidden" type="button" data-photo-edit>AJUSTAR RECORTE</button>
                    <button class="btn-secondary hidden" type="button" data-photo-change>CAMBIAR NUEVAMENTE</button>
                    <button class="btn-secondary hidden" type="button" data-photo-remove>{{ $cancelLabel }}</button>
                </div>
            </div>
        </div>
    </div>
    <x-forms.field-error :name="$name" />

    <div class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/70 p-3 sm:p-6" data-crop-modal role="dialog"
        aria-modal="true" aria-hidden="true" aria-labelledby="{{ $name }}-crop-title">
        <div class="mx-auto flex min-h-full max-w-3xl items-center justify-center">
            <section class="w-full overflow-hidden rounded-lg bg-white shadow-xl">
                <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-3">
                    <div>
                        <h2 id="{{ $name }}-crop-title" class="text-lg font-black text-[#0b1f3a]">AJUSTAR FOTOGRAFÍA</h2>
                        <p class="mt-1 text-sm text-slate-600">Centra tu rostro dentro del cuadro. Puedes mover la imagen y ajustar el tamaño.</p>
                    </div>
                    <button class="grid h-10 w-10 shrink-0 place-items-center rounded text-xl font-bold text-slate-600 hover:bg-slate-100"
                        type="button" data-crop-close aria-label="Cerrar recortador">×</button>
                </header>
                <div class="bg-slate-900 p-2 sm:p-4">
                    <div class="mx-auto h-[min(58vh,520px)] w-full overflow-hidden">
                        <img class="max-w-full" data-crop-image alt="Fotografía para recortar">
                    </div>
                </div>
                <div class="grid gap-3 border-t border-slate-200 px-4 py-3 sm:grid-cols-[auto_1fr_auto_auto] sm:items-center">
                    <button class="btn-secondary" type="button" data-crop-zoom-out aria-label="Alejar fotografía">MENOS</button>
                    <label class="grid gap-1 text-xs font-bold uppercase text-slate-500">
                        Zoom
                        <input class="w-full accent-[#d4af37]" type="range" min="0.5" max="2.5" step="0.05" value="1"
                            data-crop-zoom aria-label="Nivel de zoom">
                    </label>
                    <button class="btn-secondary" type="button" data-crop-zoom-in aria-label="Acercar fotografía">MÁS</button>
                    <button class="btn-secondary" type="button" data-crop-reset>RESTABLECER</button>
                </div>
                <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 p-4 sm:flex-row sm:justify-end">
                    <button class="btn-secondary" type="button" data-crop-cancel>CANCELAR</button>
                    <button class="btn-primary" type="button" data-crop-confirm>APLICAR RECORTE</button>
                </footer>
            </section>
        </div>
    </div>
</div>
