<?php

declare(strict_types=1);

namespace Waterfront\Domain\MailManagement\Listeners;

use Illuminate\Contracts\Bus\Dispatcher;
use Waterfront\Domain\MailManagement\Jobs\TerminateMailOnlyHosting as TerminateMailOnlyHostingJob;
use Waterfront\Support\Interfaces\Events\MailableEventInterface;

class HostingMailOnlyTerminationListener
{
    public function __construct(
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function handle(MailableEventInterface $event): void
    {
        $this->jobDispatcher->dispatch(new TerminateMailOnlyHostingJob($event));
    }
}
