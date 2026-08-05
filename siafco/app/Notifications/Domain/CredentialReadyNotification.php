<?php

namespace App\Notifications\Domain;

class CredentialReadyNotification
{
    public function __construct(public readonly int $credentialId, public readonly int $affiliateId) {}
}
