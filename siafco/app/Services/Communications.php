<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Communication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Communications
{
    public static function eligible()
    {
        return Affiliate::query()->where('status', 'activo')->whereHas('user', fn ($q) => $q
            ->where('is_active', true)->where('role', 'afiliado')->where('user_type', 'affiliate'));
    }

    public static function audience(array $selection)
    {
        $query = self::eligible();
        if (empty($selection['all'])) {
            $query->where(fn ($q) => $q->whereIn('sector_id', $selection['sectors'] ?? [])
                ->orWhereIn('id', $selection['affiliates'] ?? []));
        }

        return $query;
    }

    public static function inbox(User $user)
    {
        $affiliate = self::eligible()->where('user_id', $user->id)->firstOrFail();

        return DB::table('communication_recipients as r')->join('communications as c', 'c.id', '=', 'r.communication_id')
            ->where('r.affiliate_id', $affiliate->id)->where('c.status', 'published')
            ->where(fn ($q) => $q->whereNull('c.expires_at')->orWhere('c.expires_at', '>', now()))
            ->select('c.id', 'c.title', 'c.summary', 'c.priority', 'c.publish_at', 'c.expires_at', 'c.requires_confirmation', 'r.opened_at', 'r.confirmed_at')
            ->selectSub(DB::table('communication_attachments')->select('id')->whereColumn('communication_id', 'c.id')->where('cover', true)->limit(1), 'cover_id');
    }

    public static function audit(Communication $item, string $event, ?int $actor): void
    {
        DB::table('communication_audit_logs')->insert(['communication_id' => $item->id, 'user_id' => $actor, 'event' => $event, 'created_at' => now()]);
    }

    public static function publish(Communication $item, int $actor, int $expected, ?string $schedule): void
    {
        DB::transaction(function () use ($item, $actor, $expected, $schedule) {
            $item = Communication::lockForUpdate()->findOrFail($item->id);
            if (in_array($item->status, ['published', 'scheduled'], true)) {
                return;
            }
            abort_unless($item->status === 'draft', 409);
            $ids = self::audience($item->audience)->orderBy('id')->lockForUpdate()->pluck('id');
            if (! $ids->count() || $ids->count() !== $expected) {
                throw ValidationException::withMessages(['expected' => 'La audiencia cambió o está vacía. Revise el total.']);
            }
            if ($item->expires_at && $item->expires_at->lte($schedule ?? now())) {
                throw ValidationException::withMessages(['expires_at' => 'El vencimiento debe ser posterior a la publicación.']);
            }
            foreach ($ids->chunk(200) as $chunk) {
                DB::table('communication_recipients')->insert($chunk->map(fn ($id) => ['communication_id' => $item->id, 'affiliate_id' => $id, 'assigned_at' => now()])->all());
            }
            $item->update(['publisher_id' => $actor, 'publish_at' => $schedule ?? now(), 'status' => $schedule ? 'scheduled' : 'published']);
            self::audit($item, $item->status, $actor);
            if (! $schedule) {
                self::queueDeliveries($item);
            }
        }, 3);
    }

    public static function queueDeliveries(Communication $item): void
    {
        $devices = DB::table('affiliate_push_devices as d')->join('communication_recipients as r', 'r.affiliate_id', '=', 'd.affiliate_id')
            ->where('r.communication_id', $item->id)->where('d.enabled', true)
            ->selectRaw('r.communication_id, d.id, ? as available_at', [now()]);
        DB::table('communication_push_deliveries')->insertUsing(['communication_id', 'device_id', 'available_at'], $devices);
    }

    public static function sanitize(string $body): string
    {
        // Rebuild only simple formatting tags. All other bytes are escaped as text.
        $parts = preg_split('~(</?(?:p|br|b|strong|i|em|ul|ol|li)\\s*/?>)~i', $body, -1, PREG_SPLIT_DELIM_CAPTURE);

        return implode('', array_map(fn ($part,$index) => $index % 2 ? strtolower($part) : e($part),$parts,array_keys($parts)));
    }
}
