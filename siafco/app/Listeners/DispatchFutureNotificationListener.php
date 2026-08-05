<?php

namespace App\Listeners;

use App\Events\CredentialCreated;
use App\Events\PaymentConfirmed;
use App\Events\PaymentRejected;
use App\Notifications\Domain\CredentialReadyNotification;
use App\Notifications\Domain\PaymentConfirmedNotification;
use App\Notifications\Domain\PaymentRejectedNotification;
use App\Services\NotificationDispatcher;

class DispatchFutureNotificationListener
{
    public function __construct(private NotificationDispatcher $dispatcher) {}

    public function handle(object $event): void
    {
        match ($event::class) {
            PaymentConfirmed::class => $this->dispatcher->dispatch(new PaymentConfirmedNotification($event->paymentId, $event->affiliateId)),
            PaymentRejected::class => $this->dispatcher->dispatch(new PaymentRejectedNotification($event->paymentId, $event->affiliateId)),
            CredentialCreated::class => $this->dispatcher->dispatch(new CredentialReadyNotification($event->credentialId, $event->affiliateId)),
            default => null,
        };
    }
}
