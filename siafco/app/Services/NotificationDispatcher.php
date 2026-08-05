<?php

namespace App\Services;

class NotificationDispatcher
{
    public array $queued = [];

    public function dispatch(object $notification): void
    {
        $this->queued[] = $notification;
    }
}
