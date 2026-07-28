@props([
    'name', 'label', 'options', 'required' => false, 'placeholder' => 'Seleccione una opción',
    'value' => null, 'help' => null,
])

<label for="{{ $name }}" class="block" data-field-wrapper>
    <span class="form-label" data-field-label>
        {{ $label }} @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif
    </span>
    <select
        id="{{ $name }}" name="{{ $name }}"
        class="form-input @error($name) border-red-500 bg-red-50 text-red-900 @enderror"
        @if($required) required aria-required="true" @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        aria-describedby="{{ $name }}-error{{ $help ? ' '.$name.'-help' : '' }}"
        data-validate-field data-touched="{{ $errors->has($name) ? 'true' : 'false' }}"
        {{ $attributes }}
    >
        <option value="" disabled @selected(old($name, $value) === null || old($name, $value) === '')>{{ $placeholder }}</option>
        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected(old($name, $value) === $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if($help)<p id="{{ $name }}-help" class="mt-1 text-xs text-slate-500">{{ $help }}</p>@endif
    <x-forms.field-error :name="$name" />
</label>
