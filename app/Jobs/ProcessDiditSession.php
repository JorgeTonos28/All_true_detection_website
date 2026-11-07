<?php

namespace App\Jobs;

use App\Services\ProcessIdentitySession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ProcessDiditSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public int $sessionId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('didit:'.$this->sessionId))->releaseAfter(60)->expireAfter(90)];
    }

    public function handle(ProcessIdentitySession $processor): void
    {
        $processor->handle($this->sessionId);
    }
}
