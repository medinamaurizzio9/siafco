<?php

namespace App\Services;

use App\Models\SimpleInvestmentPortalNotification;
use App\Models\SimpleInvestmentScheduleNotice;

class SimpleInvestorNotificationService
{
    public function sync(): int
    {
        $created = 0;
        SimpleInvestmentScheduleNotice::query()->with('contract.investor.portalAccess')->chunkById(100, function ($notices) use (&$created): void {
            foreach ($notices as $notice) {
                $investor = $notice->contract?->investor;
                if (! $investor?->portalAccess?->is_active) {
                    continue;
                }
                $item = SimpleInvestmentPortalNotification::firstOrCreate(
                    ['idempotency_key' => 'portal:'.$notice->id],
                    ['investor_id' => $investor->id, 'contract_id' => $notice->contract_id, 'schedule_notice_id' => $notice->id,
                        'type' => $notice->notice_kind, 'title' => $this->title($notice->notice_kind),
                        'body' => 'Tienes una fecha contractual para revisar. Consulta tu calendario privado.',
                        'reference_date' => $notice->scheduled_on, 'generated_at' => now(), 'external_status' => 'not_configured']
                );
                $created += $item->wasRecentlyCreated ? 1 : 0;
            }
        });

        return $created;
    }

    private function title(string $kind): string
    {
        return match ($kind) {
            'advance_7' => 'Fecha próxima en 7 días', 'advance_3' => 'Fecha próxima en 3 días', 'due' => 'Fecha contractual de hoy', default => 'Fecha contractual pendiente de revisión'
        };
    }
}
