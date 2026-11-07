@php
    $inbox = \App\Services\Communications::inbox(auth()->user());
    $unopened = (clone $inbox)->whereNull('r.opened_at');
    $unreadCount = (clone $unopened)->count();
    $latest = (clone $inbox)->orderByDesc('c.publish_at')->orderByDesc('c.id')->limit(3)->get();
@endphp
<link rel="stylesheet" href="{{ asset('css/communication-notices.css') }}">
<details class="communication-bell">
    <summary aria-label="Mis comunicados, {{ $unreadCount }} sin leer">
        <x-ui.icon name="bell" class="h-5 w-5" />
        <span>{{ $unreadCount }}</span>
    </summary>
    <div class="communication-bell__menu">
        <h2 class="font-bold">Mis comunicados</h2>
        @forelse($latest as $notice)
            <article>
                <h3>{{ $notice->title }}</h3>
                <p class="communication-bell__excerpt">{{ \Illuminate\Support\Str::limit($notice->summary, 140) }}</p>
                <small>{{ \Illuminate\Support\Carbon::parse($notice->publish_at)->format('d/m/Y') }} · {{ $notice->opened_at ? 'Leído' : 'Nuevo' }}</small>
                <a href="{{ route('inbox.show', $notice->id) }}">Ver comunicado<span class="sr-only"> {{ $notice->title }}</span></a>
            </article>
        @empty
            <p>Sin comunicados recibidos.</p>
        @endforelse
        <a href="{{ route('inbox.index') }}">Ver todos</a>
    </div>
</details>
@if(request()->routeIs('affiliate.panel') && $unreadCount)
    @php
        // The publication event also orders scheduled drafts released after newer drafts.
        $pending = $unopened->leftJoin('communication_audit_logs as publication', function ($join) {
            $join->on('publication.communication_id', '=', 'c.id')->where('publication.event', 'published');
        })->addSelect('publication.id as publication_event')->orderByDesc('publication.id')
            ->orderByDesc('c.publish_at')->orderByDesc('c.id')->limit(5)->get();
        $watermark = (int) $pending->first()?->publication_event;
    @endphp
    @if($pending->isNotEmpty() && $watermark > session('communications.dismissed.'.auth()->id(), -1))
        @include('communications.arrival-dialog')
    @endif
@endif
<script src="{{ asset('js/communication-notices.js') }}" defer></script>
