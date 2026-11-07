<?php

use App\Services\SimpleInvestmentNoticeService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('simple-investments:prepare-notices', function () {
    $created = app(SimpleInvestmentNoticeService::class)->prepare();
    $this->info("Avisos internos preparados: {$created}");
})->purpose('Prepara avisos internos idempotentes de fechas contractuales');

Schedule::command('simple-investments:prepare-notices')->dailyAt('08:00')->withoutOverlapping();

Schedule::command('communications:process')->everyMinute()->withoutOverlapping();
