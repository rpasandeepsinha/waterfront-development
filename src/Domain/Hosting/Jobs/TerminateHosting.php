<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Jobs;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\Hosting\Events\TerminateHosting as TerminateHostingEvent;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class TerminateHosting extends AbstractQueueableJob
{
    public function __construct(private readonly TerminateHostingEvent $event)
    {
        parent::__construct();
    }

    public function handle(HostingService $hostingService, LoggerInterface $logger): void
    {
        $logger->info(sprintf(
            'Terminating hosting for domain %s (attempts: %d)',
            $this->event->hostingDeployment->subscription->domain,
            $this->attempts()
        ));

        $provider = $this->event->hostingDeployment->provider;
        if ($provider === null) {
            throw new RuntimeException(
                "Cannot resolve hosting provider for {$this->event->hostingDeployment->subscription->domain} upon termination."
            );
        }

        $hostingService->terminate($this->event->hostingDeployment);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::TERMINATE_HOSTING;
    }
}
