@props(['name' => 'photo', 'required' => true])

<div class="sm:col-span-2" data-photo-cropper data-field-wrapper>
    <span class="form-label" data-field-label>
        Fotografía para credencial @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif
    </span>
    <div class="rounded-lg border border-slate-300 bg-slate-50 p-4 @error($name) border-red-500 bg-red-50 @enderror" data-photo-box>
        <input id="{{ $name }}-source" class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" data-photo-source>
        <input id="{{ $name }}" class="sr-only" type="file" name="{{ $name }}" accept="image/jpeg" data-photo-output
            aria-required="{{ $required ? 'true' : 'false' }}" aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
            aria-describedby="{{ $name }}-error {{ $name }}-status" data-validate-field data-touched="{{ $errors->has($name) ? 'true' : 'false' }}">

        <div class="grid gap-4 sm:grid-cols-[180px_1fr] sm:items-center">
            <div class="aspect-square w-full max-w-[180px] overflow-hidden rounded border-2 border-dashed border-slate-300 bg-white" data-photo-preview>
                <div class="grid h-full place-items-center p-4 text-center text-sm text-slate-500" data-photo-placeholder>Selecciona una fotografía</div>
                <img class="hidden h-full w-full object-cover" data-photo-preview-image alt="Vista previa de fotografía para credencial">
            </div>
            <div>
                <p id="{{ $name }}-status" class="text-sm text-slate-600" data-photo-status>JPG, PNG o WEBP. La imagen final será cuadrada.</p>
                <p class="mt-1 hidden text-sm font-bold text-emerald-800" data-photo-details></p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <label for="{{ $name }}-source" class="btn-primary cursor-pointer" data-photo-select>Seleccionar fotografía</label>
                    <button class="btn-secondary hidden" type="button" data-photo-edit>Recortar fotografía</button>
                    <button class="btn-secondary hidden" type="button" data-photo-change>Cambiar fotografía</button>
                    <button class="btn-danger hidden" type="button" data-photo-remove>Eliminar</button>
                </div>
            </div>
        </div>
    </div>
    <x-forms.field-error :name="$name" />

    <div class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/70 p-3 sm:p-6" data-crop-modal role="dialog" aria-modal="true" aria-labelledby="{{ $name }}-crop-title">
        <div class="mx-auto flex min-h-full max-w-3xl items-center justify-center">
            <section class="w-full overflow-hidden rounded-lg bg-white shadow-xl">
                <header class="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                    <h2 id="{{ $name }}-crop-title" class="text-lg font-black text-[#0b1f3a]">Recortar fotografía</h2>
                    <button class="grid h-10 w-10 place-items-center rounded text-xl font-bold text-slate-600 hover:bg-slate-100" type="button" data-crop-close aria-label="Cerrar recortador">×</button>
                </header>
                <div class="bg-slate-900 p-2 sm:p-4">
                    <div class="mx-auto h-[min(70vh,600px)] w-full overflow-hidden"><img class="max-w-full" data-crop-image alt="Fotografía para recortar"></div>
                </div>
                <footer class="flex flex-col-reverse gap-2 border-t border-slate-200 p-4 sm:flex-row sm:justify-end">
                    <button class="btn-secondary" type="button" data-crop-cancel>Cancelar</button>
                    <button class="btn-primary" type="button" data-crop-confirm>Confirmar recorte</button>
                </footer>
            </section>
        </div>
    </div>
</div>
