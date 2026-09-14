<?php

declare(strict_types=1);

namespace Waterfront\Domain\ResellerHosting\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\ResellerHosting\Services\ResellerHostingService;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class TerminateResellerHostingJob extends AbstractQueueableJob
{
    public function __construct(
        public readonly string $contactPersonName,
        public readonly string $contactEmail,
        public readonly ResellerHostingDeployment $resellerHostingDeployment,
    ) {
        parent::__construct();
    }

    public function handle(ResellerHostingService $resellerHostingService, LoggerInterface $logger): void
    {
        $logger->info(sprintf(
            'Terminating resellerhosting for subscription %s (attempts: %d)',
            $this->resellerHostingDeployment->subscription_uuid,
            $this->attempts(),
        ));

        $resellerHostingService->terminate($this->resellerHostingDeployment);
    }

    protected function getQueueName(): QueueName
    {
        return QueueName::TERMINATE_HOSTING;
    }
}
