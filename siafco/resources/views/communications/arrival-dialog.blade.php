<dialog class="communication-dialog" aria-labelledby="communication-dialog-title">
    <header>
        <h2 id="communication-dialog-title">Comunicados nuevos</h2>
        <p>{{ $unreadCount }} {{ $unreadCount === 1 ? 'comunicado sin leer' : 'comunicados sin leer' }}</p>
    </header>
    <div class="communication-dialog__list" tabindex="0" role="region" aria-label="Comunicados pendientes">
        @foreach($pending as $notice)
            <article>
                <div class="communication-dialog__meta">
                    <time datetime="{{ \Illuminate\Support\Carbon::parse($notice->publish_at)->toIso8601String() }}">{{ \Illuminate\Support\Carbon::parse($notice->publish_at)->format('d/m/Y') }}</time>
                    <span>Prioridad {{ mb_strtolower(__('communications.'.$notice->priority, [], 'es')) }}</span>
                </div>
                <h3>{{ $notice->title }}</h3>
                <p>{{ $notice->summary }}</p>
                @if($notice->cover_id)
                    <img loading="lazy" alt="Portada del comunicado" src="{{ route('inbox.file', [$notice->id, $notice->cover_id]) }}">
                @endif
                <a class="btn-primary" href="{{ route('inbox.show', $notice->id) }}">Leer comunicado<span class="sr-only"> {{ $notice->title }}</span></a>
            </article>
        @endforeach
    </div>
    <footer>
        <a class="btn-secondary" href="{{ route('inbox.index') }}">Ver todos</a>
        <form method="post" action="{{ route('inbox.dismiss') }}">
            @csrf
            <input type="hidden" name="publication" value="{{ $watermark }}">
            <button type="submit" class="btn-secondary" autofocus>Ahora no</button>
        </form>
    </footer>
</dialog>
