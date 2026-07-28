@props(['name'])

<p id="{{ $name }}-error" class="mt-1 text-sm font-medium text-red-600 {{ $errors->has($name) ? '' : 'hidden' }}" data-field-error="{{ $name }}" role="alert">
    @error($name){{ $message }}@enderror
</p>
