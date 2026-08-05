<?php

namespace App\Notifications\Domain;

class PaymentRejectedNotification
{
    public function __construct(public readonly int $paymentId, public readonly int $affiliateId) {}
}
