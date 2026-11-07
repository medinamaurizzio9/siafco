<article class="space-y-1 border-b p-2">@if($item->cover_id)<img loading="lazy" width="100" alt="" src="{{ route('inbox.file',[$item->id,$item->cover_id]) }}">@endif
<a class="font-bold" href="{{ route('inbox.show',$item->id) }}">{{ $item->title }}</a><p>{{ $item->summary }}</p><small>{{ $item->publish_at }} · {{ $item->opened_at ? 'Leído' : 'No leído' }}</small></article>
