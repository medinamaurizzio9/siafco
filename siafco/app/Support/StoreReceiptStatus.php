<?php

namespace App\Support;

final class StoreReceiptStatus
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const APPROVED = self::CONFIRMED;
    public const REJECTED = 'rejected';

    public const ALL = [self::PENDING, self::APPROVED, self::REJECTED];
}
