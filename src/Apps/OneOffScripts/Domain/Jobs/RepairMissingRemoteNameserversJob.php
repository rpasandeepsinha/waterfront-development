<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Apps\OneOffScripts\Domain\Services\FailedDomainSubscriptionRepairService;

class RepairMissingRemoteNameserversJob extends RepairFailedDomainSubscriptionJob
{
    public function handle(
        FailedDomainSubscriptionRepairService $failedDomainSubscriptionRepairService,
        LoggerInterface $logger,
    ): void {
        $domainDeployment = $this->subscription->domainDeployment;

        $this->logDryRunOrExecuting(
            logger: $logger,
            dryRunMessage: 'Dry run: would repair missing RTR nameservers for failed domain subscription.',
            executingMessage: 'Repairing missing RTR nameservers for failed domain subscription.',
            domainDeployment: $domainDeployment,
        );

        if ($this->dryRun) {
            return;
        }

        $failedDomainSubscriptionRepairService->repairMissingRemoteNameservers(
            subscription: $this->subscription,
            source: $this->triggeredBy,
        );
    }
}
