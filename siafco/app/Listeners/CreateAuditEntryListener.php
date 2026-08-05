<?php

namespace App\Listeners;

use App\Events\AffiliateActivated;
use App\Events\AffiliateAccessBlocked;
use App\Events\AffiliateAccessEnabled;
use App\Events\CredentialActivated;
use App\Events\CredentialCreated;
use App\Events\CredentialRevoked;
use App\Events\PaymentConfirmed;
use App\Events\PaymentRejected;
use App\Events\PaymentVoided;
use App\Models\Affiliate;
use App\Models\AffiliationPayment;
use App\Models\DigitalCredential;
use App\Services\AuditService;

class CreateAuditEntryListener
{
    public function handle(object $event): void
    {
        match ($event::class) {
            PaymentConfirmed::class => $this->payment('payment_confirmed', $event),
            PaymentRejected::class => $this->payment('payment_rejected', $event),
            PaymentVoided::class => $this->payment('payment_voided', $event),
            AffiliateActivated::class => $this->affiliate('affiliate_activated', $event),
            AffiliateAccessBlocked::class => $this->affiliate('affiliate_access_blocked', $event),
            AffiliateAccessEnabled::class => $this->affiliate('affiliate_access_enabled', $event),
            CredentialCreated::class => $this->credential('credential_created', $event),
            CredentialActivated::class => $this->credential('credential_activated', $event),
            CredentialRevoked::class => $this->credential('credential_revoked', $event),
            default => null,
        };
    }

    private function payment(string $action, PaymentConfirmed|PaymentRejected|PaymentVoided $event): void
    {
        $payment = AffiliationPayment::find($event->paymentId);
        AuditService::record($action, $payment, $event->metadata + [
            'payment_public_id' => 'PAY-'.$event->paymentId,
            'affiliate_id' => $event->affiliateId,
            'actor_id' => $event->actorId,
            'status' => $event->status,
        ]);
    }

    private function affiliate(string $action, AffiliateActivated|AffiliateAccessBlocked|AffiliateAccessEnabled $event): void
    {
        AuditService::record($action, Affiliate::find($event->affiliateId), $event->metadata);
    }

    private function credential(string $action, CredentialCreated|CredentialActivated|CredentialRevoked $event): void
    {
        AuditService::record($action, DigitalCredential::find($event->credentialId), $event->metadata + [
            'affiliate_id' => $event->affiliateId,
        ]);
    }
}
