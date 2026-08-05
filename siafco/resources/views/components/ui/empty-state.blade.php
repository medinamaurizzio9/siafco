@props(['title', 'message' => null, 'icon' => 'inbox'])

<div {{ $attributes->class(['empty-state']) }}>
    <span class="empty-state-icon" aria-hidden="true">
        <x-ui.icon :name="$icon" class="h-6 w-6" />
    </span>
    <div>
        <p class="font-black text-siafco-primary-900">{{ $title }}</p>
        @if($message)<p class="mt-1 text-sm">{{ $message }}</p>@endif
    </div>
    @if(trim($slot) !== '')
        <div>{{ $slot }}</div>
    @endif
</div>
