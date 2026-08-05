<?php

namespace App\Listeners;

use App\Events\AffiliateActivated;
use App\Jobs\GenerateCredentialFilesJob;
use App\Models\Affiliate;

class GenerateCredentialListener
{
    public function handle(AffiliateActivated $event): void
    {
        $credential = Affiliate::find($event->affiliateId)?->credential;

        if ($credential) {
            GenerateCredentialFilesJob::dispatch($credential->id);
        }
    }
}
