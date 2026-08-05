@props(['search' => false])

<div {{ $attributes->class(['table-wrap']) }}>
    @if($search)
        <div class="border-b border-siafco-border p-3">
            {{ $search }}
        </div>
    @endif
    <div class="overflow-x-auto">
        <table class="table">
            {{ $slot }}
        </table>
    </div>
</div>
