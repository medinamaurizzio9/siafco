<?php

namespace App\Console\Commands;

use App\Jobs\SendCommunicationPush;
use App\Models\Communication;
use App\Services\Communications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessCommunications extends Command
{
    protected $signature = 'communications:process';

    protected $description = 'Publica comunicados programados y despacha entregas pendientes';

    public function handle(): void
    {
        Communication::where('status', 'scheduled')->where('publish_at', '<=', now())->chunkById(100, function ($items) {
            foreach ($items as $item) {
                DB::transaction(function () use ($item) {
                    $item = Communication::lockForUpdate()->find($item->id);
                    if ($item->status === 'scheduled') {
                        $item->update(['status' => 'published']);
                        Communications::audit($item, 'published', $item->publisher_id);
                        Communications::queueDeliveries($item);
                    }
                });
            }
        });
        $deliveries = DB::table('communication_push_deliveries');
        (clone $deliveries)->where('status', 'sending')->where('available_at', '<=', now())->update(['status' => 'unknown', 'result' => 'worker interrupted']);
        (clone $deliveries)->where('status', 'pending')->where('available_at', '<=', now())->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                SendCommunicationPush::dispatch($row->id)->onQueue('communications');
            }
        });
    }
}
