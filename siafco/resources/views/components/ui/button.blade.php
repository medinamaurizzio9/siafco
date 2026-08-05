@props([
    'variant' => 'primary',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'loading' => false,
])

@php
    $class = match ($variant) {
        'secondary' => 'btn-secondary',
        'outline' => 'btn-outline',
        'ghost' => 'btn-ghost',
        'success' => 'btn-success',
        'warning' => 'btn-warning',
        'danger' => 'btn-danger',
        'icon' => 'btn-icon',
        default => 'btn-primary',
    };
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$class]) }}>
        @if($icon)<x-ui.icon :name="$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$class]) }} @disabled($loading || $attributes->get('disabled'))>
        @if($loading)
            <span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-r-transparent" aria-hidden="true"></span>
        @elseif($icon)
            <x-ui.icon :name="$icon" class="h-4 w-4" />
        @endif
        {{ $slot }}
    </button>
@endif
