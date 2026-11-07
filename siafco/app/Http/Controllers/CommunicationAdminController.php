<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Models\Communication;
use App\Models\Sector;
use App\Services\Communications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CommunicationAdminController extends Controller
{
    public function index(Request $r)
    {
        $filters = $r->validate(['q' => 'nullable|string|max:200', 'status' => 'nullable|in:draft,scheduled,published,archived']);
        $items = Communication::query()->when($filters['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', "%$v%"))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))->latest('id')->paginate(20)->withQueryString();

        return view('communications.admin-list', compact('items'));
    }

    public function edit(?Communication $item = null)
    {
        $item ??= new Communication;
        abort_if($item->exists && $item->status !== 'draft', 409);

        return view('communications.edit', ['item' => $item, 'sectors' => Sector::orderBy('name')->get()]);
    }

    public function audienceCount(Request $request)
    {
        $selection = $request->validate([
            'all' => ['required', 'boolean'],
            'sectors' => ['present', 'array', 'list'],
            'sectors.*' => ['integer', 'min:1'],
            'affiliates' => ['present', 'array', 'list'],
            'affiliates.*' => ['integer', 'min:1'],
        ]);

        return response()->json(['count' => Communications::audience($selection)->count()]);
    }

    public function candidates(Request $r)
    {
        $data = $r->validate(['q' => 'nullable|string|max:150', 'sector' => 'nullable|integer', 'status' => 'nullable|string|max:40', 'after' => 'nullable|integer|min:0']);
        $rows = Affiliate::query()->select('id', 'full_name', 'registration_number', 'ci', 'status')
            ->selectSub(Communications::eligible()->selectRaw('1')->whereColumn('affiliates.id', 'candidate.id')->limit(1), 'eligible')
            ->from('affiliates as candidate')->withoutGlobalScopes()->whereNull('candidate.deleted_at')
            ->when($data['q'] ?? null, fn ($q, $v) => $q->where(fn ($s) => $s->where('full_name', 'like', "%$v%")->orWhere('ci', 'like', "%$v%")->orWhere('registration_number', 'like', "%$v%")))
            ->when($data['sector'] ?? null, fn ($q, $v) => $q->where('sector_id', $v))
            ->when($data['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->where('id', '>', $data['after'] ?? 0)->orderBy('id')->limit(100)->get();

        return response()->json($rows);
    }

    public function save(Request $r, ?Communication $item = null)
    {
        $data = $r->validate(['title' => 'required|string|max:200', 'summary' => 'required|string|max:500', 'body' => 'required|string|max:60000',
            'priority' => 'required|in:normal,important,urgent', 'expires_at' => 'nullable|date|after:now', 'requires_confirmation' => 'sometimes|boolean',
            'all' => 'sometimes|boolean', 'sectors' => 'nullable|array', 'sectors.*' => 'integer|exists:sectors,id', 'affiliate_ids' => 'nullable|json',
            'cover' => 'nullable|image|mimes:jpeg,png,webp|extensions:jpg,jpeg,png,webp|max:4096', 'files' => 'nullable|array|max:8',
            'files.*' => 'required|file|mimes:jpeg,png,webp,pdf|extensions:jpg,jpeg,png,webp,pdf|max:8192']);
        foreach (array_filter([$r->file('cover'), ...$r->file('files', [])]) as $upload) {
            $valid = ['image/jpeg' => ['jpg', 'jpeg'], 'image/png' => ['png'], 'image/webp' => ['webp'], 'application/pdf' => ['pdf']];
            if (! in_array(strtolower($upload->getClientOriginalExtension()), $valid[$upload->getMimeType()] ?? [], true)) {
                throw ValidationException::withMessages(['files' => 'La extensión no corresponde al tipo de archivo.']);
            }
        }
        $ids = json_decode($data['affiliate_ids'] ?? '[]', true);
        validator(['ids' => $ids], ['ids' => 'present|array|list', 'ids.*' => 'integer|min:1'])->validate();
        $paths = [];
        try {
            $item = DB::transaction(function () use ($r, $item, $data, $ids, &$paths) {
                $item = $item?->exists ? Communication::lockForUpdate()->findOrFail($item->id) : new Communication(['author_id' => $r->user()->id, 'status' => 'draft']);
                abort_unless($item->status === 'draft', 409);
                $event = $item->exists ? 'edited' : 'created';
                $item->fill($r->only('title', 'summary', 'priority', 'expires_at'));
                $item->body = Communications::sanitize($data['body']);
                $item->requires_confirmation = $r->boolean('requires_confirmation');
                $item->audience = ['all' => $r->boolean('all'), 'sectors' => $data['sectors'] ?? [], 'affiliates' => array_values(array_unique($ids))];
                $item->save();
                if ($r->hasFile('cover')) {
                    $old = DB::table('communication_attachments')->where('communication_id', $item->id)->where('cover', true)->first();
                    if ($old) {
                        DB::table('communication_attachments')->where('id', $old->id)->delete();
                        DB::afterCommit(fn () => Storage::disk('local')->delete($old->path));
                    }
                }
                foreach (array_filter([$r->file('cover'), ...$r->file('files', [])]) as $file) {
                    $path = $file->store('communications', 'local');
                    throw_unless($path, new \RuntimeException('Error de almacenamiento.'));
                    $paths[] = $path;
                    DB::table('communication_attachments')->insert(['communication_id' => $item->id, 'path' => $path, 'name' => mb_substr(basename($file->getClientOriginalName()), 0, 200), 'cover' => $file === $r->file('cover')]);
                }
                Communications::audit($item, $event, $r->user()->id);

                return $item;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($paths);
            throw $error;
        }

        return redirect()->route('communications.preview', $item);
    }

    public function preview(Communication $item)
    {
        $count = $item->status === 'draft' ? Communications::audience($item->audience)->count() : DB::table('communication_recipients')->where('communication_id', $item->id)->count();

        return view('communications.preview', ['item' => $item, 'count' => $count, 'files' => DB::table('communication_attachments')->where('communication_id', $item->id)->get(),
            'sample' => $item->status === 'draft' ? Communications::audience($item->audience)->orderBy('id')->paginate(25) : null]);
    }

    public function publish(Request $r, Communication $item)
    {
        $data = $r->validate(['expected' => 'required|integer|min:1', 'schedule' => 'nullable|date|after:now']);
        Communications::publish($item, $r->user()->id, $data['expected'], $data['schedule'] ?? null);

        return back();
    }

    public function archive(Request $r, Communication $item)
    {
        DB::transaction(function () use ($r, $item) {
            $item = Communication::lockForUpdate()->findOrFail($item->id);
            if ($item->status !== 'archived') {
                $item->update(['status' => 'archived']);
                Communications::audit($item, 'archived', $r->user()->id);
            }
        });

        return back();
    }

    public function report(Communication $item)
    {
        $query = DB::table('communication_recipients as r')->join('affiliates as a', 'a.id', '=', 'r.affiliate_id')->where('communication_id', $item->id);

        return view('communications.report', ['item' => $item, 'stats' => (clone $query)->selectRaw('count(*) as total,count(opened_at) as opened,count(confirmed_at) as confirmed')->first(),
            'rows' => $query->select('full_name', 'registration_number', 'assigned_at', 'opened_at', 'confirmed_at')->orderBy('r.id')->paginate(30)]);
    }

    public function file(Communication $item,int $file)
    {
        $row = DB::table('communication_attachments')->where('communication_id',$item->id)->where('id',$file)->firstOrFail();

        return Storage::disk('local')->download($row->path,$row->name);
    }
}
