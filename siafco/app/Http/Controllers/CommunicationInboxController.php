<?php

namespace App\Http\Controllers;

use App\Http\Responses\MobileApiResponse;
use App\Models\Affiliate;
use App\Services\Communications;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CommunicationInboxController extends Controller
{
    public function index(Request $r)
    {
        $v = $r->validate(['q' => 'nullable|string|max:200', 'filter' => 'nullable|in:all,read,unread', 'page' => 'sometimes|integer|min:1']);
        $query = Communications::inbox($r->user())->when($v['q'] ?? null, fn ($q, $s) => $q->where('c.title', 'like', "%$s%"));
        match ($v['filter'] ?? '') {
            'read' => $query->whereNotNull('r.opened_at'),
            'unread' => $query->whereNull('r.opened_at'),
            default => null
        };
        $items = $query->orderByDesc('c.publish_at')->orderByDesc('c.id')->paginate(20)->withQueryString();

        return $r->is('api/*') ? MobileApiResponse::success($items->toArray()) : view('communications.inbox', compact('items'));
    }

    public function dismiss(Request $request)
    {
        abort_unless(Communications::eligible()->where('user_id', $request->user()->id)->exists(), 403);
        $data = $request->validate(['publication' => 'required|integer|min:0']);
        $key = 'communications.dismissed.'.$request->user()->id;
        $request->session()->put($key, max($request->session()->get($key, -1), (int) $data['publication']));

        return redirect()->route('affiliate.panel');
    }

    public function count(Request $r)
    {
        return MobileApiResponse::success(['unread_count' => Communications::inbox($r->user())->whereNull('r.opened_at')->count()]);
    }

    public function show(Request $r, int $id)
    {
        $item = Communications::inbox($r->user())->addSelect('c.body', 'r.id as recipient')->where('c.id', $id)->firstOrFail();
        $opened = now();
        DB::table('communication_recipients')->where('id', $item->recipient)->whereNull('opened_at')->update(['opened_at' => $opened]);
        $item->opened_at = DB::table('communication_recipients')->where('id', $item->recipient)->value('opened_at');
        unset($item->recipient);
        $files = DB::table('communication_attachments')->where('communication_id', $id)->get(['id', 'name', 'cover']);

        return $r->is('api/*') ? MobileApiResponse::success(['communication' => $item, 'attachments' => $files]) : view('communications.detail', compact('item', 'files'));
    }

    public function read(Request $r, int $id)
    {
        $item = Communications::inbox($r->user())->addSelect('r.id as recipient')->where('c.id', $id)->firstOrFail();
        $row = DB::table('communication_recipients')->where('id', $item->recipient);
        $updates = ['opened_at' => DB::raw('COALESCE(opened_at,CURRENT_TIMESTAMP)')];
        if ($item->requires_confirmation) {
            $updates['confirmed_at'] = DB::raw('COALESCE(confirmed_at,CURRENT_TIMESTAMP)');
        }
        $row->update($updates);

        return $r->is('api/*') ? MobileApiResponse::success(['read' => true]) : back();
    }

    public function file(Request $r, int $id, int $file)
    {
        Communications::inbox($r->user())->where('c.id', $id)->firstOrFail();
        $row = DB::table('communication_attachments')->where('communication_id', $id)->where('id', $file)->firstOrFail();
        $storage = Storage::disk('local');
        if ($row->cover) {
            return $storage->response($row->path, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
        }

        return $storage->download($row->path, $row->name, ['Cache-Control' => 'private, no-store']);
    }

    public function device(Request $r)
    {
        $data = $r->validate(['device_id' => 'required|uuid', 'token' => 'required|string|max:4096', 'enabled' => 'required|boolean']);
        $owner = $r->user()->affiliate->id;
        $hash = hash('sha256', $data['token']);
        try {
            DB::transaction(function () use ($owner, $data, $hash) {
                Affiliate::whereKey($owner)->lockForUpdate()->firstOrFail();
                $keys = ['affiliate_id' => $owner, 'device_id' => $data['device_id']];
                DB::table('affiliate_push_devices')->updateOrInsert($keys, ['token_hash' => $hash, 'token' => Crypt::encryptString($data['token']), 'enabled' => $data['enabled']]);
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['token' => 'No se pudo registrar el dispositivo.']);
        }

        return MobileApiResponse::success(['device_id' => $data['device_id']]);
    }

    public function revoke(Request $r, string $device)
    {
        DB::table('affiliate_push_devices')->where('affiliate_id', $r->user()->affiliate->id)->where('device_id',$device)->delete();

        return MobileApiResponse::success();
    }
}
