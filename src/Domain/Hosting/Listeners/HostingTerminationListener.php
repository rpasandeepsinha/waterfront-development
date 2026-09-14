<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Listeners;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\Hosting\Events\TerminateHosting;
use Waterfront\Domain\Hosting\Jobs\TerminateHosting as TerminateHostingJob;

class HostingTerminationListener
{
    public function __construct(
        private readonly Dispatcher $dispatcher,
    ) {
    }

    public function handle(TerminateHosting $event): void
    {
        $this->dispatcher->dispatch(new TerminateHostingJob($event));
    }
}
