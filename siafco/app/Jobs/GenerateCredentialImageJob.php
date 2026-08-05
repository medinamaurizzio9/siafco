<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCredentialImageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $credentialId) {}

    public function handle(): void {}
}
