<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\Redirects\Services\RedirectService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class TerminateRedirectsJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly Subscription $subscription,
    ) {
        parent::__construct();
    }

    public function handle(RedirectService $redirectService, LoggerInterface $logger): void
    {
        $logger->info(sprintf(
            'Terminating redirects for domain %s',
            $this->subscription->domain,
        ));

        $redirectService->terminateRedirect($this->subscription);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::HOSTING;
    }
}
