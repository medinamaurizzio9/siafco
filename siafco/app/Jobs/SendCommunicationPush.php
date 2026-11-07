<?php

namespace App\Jobs;

use App\Models\Communication;
use App\Services\CommunicationFcm;
use App\Services\Communications;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class SendCommunicationPush implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(CommunicationFcm $fcm): void
    {
        $row = DB::table('communication_push_deliveries')->where('id', $this->deliveryId);
        if (! (clone $row)->where('status', 'pending')->where('available_at', '<=', now())->update(['status' => 'sending', 'available_at' => now()->addMinutes(5), 'attempts' => DB::raw('attempts+1')])) {
            return;
        }
        $delivery = $row->first();
        if (! $delivery) {
            return;
        }
        $device = DB::table('affiliate_push_devices')->where('id', $delivery->device_id)->where('enabled', true)->first();
        $item = Communication::find($delivery->communication_id);
        if (! $device || ! $item || $item->status !== 'published' || $item->expires_at?->isPast() || ! Communications::eligible()->whereKey($device->affiliate_id)->exists()) {
            $row->update(['status' => 'skipped']);

            return;
        }
        try {
            $response = $fcm->send(Crypt::decryptString($device->token), $item);
            $result = 'HTTP '.$response->status();
            $retry = $response->serverError() || in_array($response->status(), [401, 429], true);
            $status = $response->successful() ? 'sent' : 'failed';
            if (collect($response->json('error.details', []))->contains('errorCode', 'UNREGISTERED')) {
                DB::table('affiliate_push_devices')->where('id', $device->id)->where('token_hash', $device->token_hash)->update(['enabled' => false]);
                $retry = false;
            }
        } catch (\Throwable $e) {
            $result = class_basename($e);
            $status = 'failed';
            $retry = true;
        }
        $row->update(['status' => $retry && $delivery->attempts < 5 ? 'pending' : $status, 'result' => $result,
            'available_at' => now()->addSeconds(max(60 * 2 ** ($delivery->attempts - 1), (int) ($response ?? null)?->header('Retry-After')))]);
    }
}
