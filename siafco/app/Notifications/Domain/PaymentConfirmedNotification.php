<?php

namespace App\Notifications\Domain;

class PaymentConfirmedNotification
{
    public function __construct(public readonly int $paymentId, public readonly int $affiliateId) {}
}
