<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\DigitalCredential;

class SiafcoHealthCheckService
{
    public function findings(): array
    {
        return [
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
        ];
    }
}
