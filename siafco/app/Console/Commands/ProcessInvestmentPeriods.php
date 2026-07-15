<?php

namespace App\Console\Commands;

use App\Models\InvestmentLot;
use App\Models\InvestmentReturnPeriod;
use App\Models\ShareReservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessInvestmentPeriods extends Command
{
    protected $signature = 'investments:process-periods';

    protected $description = 'Procesa reservas vencidas, maduracion de lotes y periodos de rendimientos sin duplicar informacion.';

    public function handle(): int
    {
        try {
            $expiredReservations = ShareReservation::whereIn('status', ['pending', 'active'])
                ->whereDate('expiration_date', '<', now())
                ->update([
                    'status' => 'expired',
                    'closure_reason' => 'Reserva vencida automaticamente por el comando diario.',
                    'updated_at' => now(),
                ]);

            $maturedLots = InvestmentLot::where('status', 'active_waiting')
                ->whereDate('maturity_date', '<=', now())
                ->update(['status' => 'active_earning', 'updated_at' => now()]);

            $pendingPeriods = InvestmentReturnPeriod::where('status', 'upcoming')
                ->whereDate('due_date', '<=', now())
                ->update(['status' => 'pending', 'updated_at' => now()]);

            $this->info("Reservas vencidas: {$expiredReservations}");
            $this->info("Lotes maduros: {$maturedLots}");
            $this->info("Periodos pendientes activados: {$pendingPeriods}");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            Log::error('Error processing investment periods', ['message' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
