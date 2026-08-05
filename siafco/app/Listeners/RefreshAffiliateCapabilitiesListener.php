<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Cache;

class RefreshAffiliateCapabilitiesListener
{
    public function handle(object $event): void
    {
        if (property_exists($event, 'affiliateId')) {
            Cache::forget('mobile.affiliate.capabilities.'.$event->affiliateId);
        }
    }
}
