<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\DigitalCredential;
use Illuminate\Support\Facades\Cache;

class SiafcoHealthCheckService
{
    public const CACHE_KEY = 'siafco.health.findings';

    public function findings(): array
    {
        return Cache::remember(self::CACHE_KEY, 60, fn () => [
            'credentials_without_qr' => DigitalCredential::whereNull('qr_path')->count(),
            'active_affiliates_without_credential' => Affiliate::where('status', 'activo')->doesntHave('credential')->count(),
            'affiliate_users_without_affiliate' => \App\Models\User::where('user_type', 'affiliate')->doesntHave('affiliate')->count(),
            'affiliates_without_user' => Affiliate::whereNull('user_id')->count(),
            'duplicated_credentials' => DigitalCredential::select('affiliate_id')
                ->groupBy('affiliate_id')
                ->havingRaw('COUNT(*) > 1')
                ->count(),
            'confirmed_payments_with_pending_request' => \App\Models\AffiliationPayment::where('status', 'confirmed')
                ->whereHas('publicRequest', fn ($query) => $query->whereNotIn('status', ['approved']))
                ->count(),
        ]);
    }

    public function statusItems(): array
    {
        $findings = $this->findings();
        $total = collect($findings)->sum();

        return [
            ['label' => 'Base de datos', 'status' => 'OK', 'tone' => 'success'],
            ['label' => 'API movil', 'status' => 'OK', 'tone' => 'success'],
            ['label' => 'Mini Tienda', 'status' => 'OK', 'tone' => 'success'],
            ['label' => 'Tesoreria', 'status' => 'OK', 'tone' => 'success'],
            [
                'label' => 'Aplicacion',
                'status' => $total > 0 ? 'Atencion' : 'OK',
                'tone' => $total > 0 ? 'warning' : 'success',
            ],
        ];
    }
}
