<x-layouts.app title="Vista previa">
<section class="card space-y-4 p-5">
<h2 class="text-xl font-bold">{{ $item->title }}</h2><p>{{ __('communications.'.$item->status, [], 'es') }} · {{ __('communications.'.$item->priority, [], 'es') }}</p><p>{{ $item->summary }}</p><article>{!! $item->body !!}</article>
@foreach($files as $file)<a class="font-bold underline" href="{{ route('communications.file',[$item,$file->id]) }}">{{ $file->name }}</a>@endforeach
<h3 class="border-t pt-4 font-bold">{{ $count }} destinatarios únicos</h3>
@if($sample) @foreach($sample as $affiliate)<div>{{ $affiliate->full_name }} · {{ $affiliate->registration_number }}</div>@endforeach {{ $sample->links() }} @endif

@if($item->status==='draft')<a class="font-bold underline" href="{{ route('communications.edit',$item) }}">Editar borrador</a>
@can('publish',$item)<form class="flex flex-wrap items-end gap-2" method="post" action="{{ route('communications.publish',$item) }}">@csrf
<input type="hidden" name="expected" value="{{ $count }}"><label class="block max-w-md"><span class="form-label">Programar para (opcional)</span><input class="form-input" type="datetime-local" name="schedule" value="{{ old('schedule') }}"></label><p>Deje la fecha vacía para publicar ahora.</p><button class="btn-primary">Confirmar publicación para {{ $count }} destinatarios</button></form>@endcan
@else<p>Para corregir el contenido, archive esta versión y publique un comunicado de corrección que identifique el original.</p>@endif
@can('publish',$item) @if($item->status!=='archived')<form class="flex flex-wrap items-end gap-2" method="post" action="{{ route('communications.archive',$item) }}">@csrf<button class="btn-secondary">Archivar comunicado</button></form>@endif @endcan
@can('report',$item)<a class="font-bold underline" href="{{ route('communications.report',$item) }}">Destinatarios y estadísticas</a>@endcan
</section>
</x-layouts.app>
