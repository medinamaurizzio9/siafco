@props(['size' => 'md', 'title' => null])

@php
    $sizeClass = match ($size) {
        'sm' => 'max-w-md',
        'lg' => 'max-w-4xl',
        'fullscreen' => 'max-w-none min-h-screen w-screen rounded-none',
        default => 'max-w-2xl',
    };
@endphp

<dialog {{ $attributes->class(['modal-panel', $sizeClass]) }}>
    @if($title)
        <header class="border-b border-siafco-border px-5 py-4">
            <h2 class="text-lg font-black text-siafco-primary-900">{{ $title }}</h2>
        </header>
    @endif
    <div class="p-5">
        {{ $slot }}
    </div>
</dialog>
