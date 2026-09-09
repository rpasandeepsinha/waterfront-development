<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Jobs;

use Psr\Log\LoggerInterface;
use RealtimeRegister\Domain\ProcessCollection;
use Waterfront\Apps\OneOffScripts\Domain\Services\FailedDomainSubscriptionRepairService;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class RestoreFromProcesses extends RepairFailedDomainSubscriptionJob
{
    public function __construct(
        public ?ProcessCollection $processCollection,
        Subscription $subscription,
        bool $dryRun,
        string $triggeredBy,
    ) {
        parent::__construct(
            subscription: $subscription,
            dryRun: $dryRun,
            triggeredBy: $triggeredBy,
        );
    }

    public function handle(
        FailedDomainSubscriptionRepairService $failedDomainSubscriptionRepairService,
        LoggerInterface $logger,
    ): void {
        $domainDeployment = $this->subscription->domainDeployment;

        $this->logDryRunOrExecuting(
            logger: $logger,
            dryRunMessage: 'Dry run: would restore subscription because RTR still has processes.',
            executingMessage: 'Restoring failed subscription from open processes.',
            domainDeployment: $domainDeployment,
            meta: [
                'processes' => $this->processCollection?->toArray(),
            ],
        );

        if ($this->dryRun) {
            return;
        }

        if ($this->processCollection === null) {
            $logger->error(
                'Process collection is null, cannot restore subscription to pending.',
                $this->buildLogContext($domainDeployment),
            );

            return;
        }

        $failedDomainSubscriptionRepairService->restoreFromProcesses(
            subscription: $this->subscription,
            source: $this->triggeredBy,
            processCollection: $this->processCollection,
        );
    }
}
