<?php

namespace App\Support;

final class StoreDeliveryMethod
{
    public const PICKUP = 'pickup';
    public const SHIPPING = 'shipping';

    public const ALL = [self::PICKUP, self::SHIPPING];
}
