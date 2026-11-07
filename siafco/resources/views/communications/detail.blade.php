<x-layouts.app title="Mis Comunicados">
<section class="card space-y-4 p-5">
<h2 class="text-xl font-bold">{{ $item->title }}</h2><p>{{ $item->summary }}</p><time>{{ $item->publish_at }}</time><article>{!! $item->body !!}</article>
@foreach($files as $file) @if($file->cover)<img alt="Portada" src="{{ route('inbox.file',[$item->id,$file->id]) }}">@else<a class="font-bold underline" href="{{ route('inbox.file',[$item->id,$file->id]) }}">{{ $file->name }}</a>@endif @endforeach
@if($item->requires_confirmation && !$item->confirmed_at)<form method="post" action="{{ route('inbox.read',$item->id) }}">@csrf<button class="btn-primary">Confirmar lectura</button></form>@elseif($item->confirmed_at)<p>Lectura confirmada el {{ $item->confirmed_at }}</p>@endif
</section>
</x-layouts.app>
