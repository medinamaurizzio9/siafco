@props(['variant' => 'info', 'title' => null, 'icon' => null])

@php
    $class = match ($variant) {
        'success' => 'alert alert-success',
        'warning' => 'alert alert-warning',
        'danger', 'error' => 'alert alert-danger',
        default => 'alert alert-info',
    };
@endphp

<div role="{{ in_array($variant, ['danger', 'error'], true) ? 'alert' : 'status' }}" {{ $attributes->class([$class, 'flex gap-3']) }}>
    @if($icon)
        <x-ui.icon :name="$icon" class="mt-0.5 h-5 w-5 shrink-0" />
    @endif
    <div>
        @if($title)<p class="font-black">{{ $title }}</p>@endif
        <div>{{ $slot }}</div>
    </div>
</div>
