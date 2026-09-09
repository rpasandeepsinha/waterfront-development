<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Jobs;

use Psr\Log\LoggerInterface;
use Waterfront\Apps\OneOffScripts\Domain\Services\FailedDomainSubscriptionRepairService;

class SyncDomainSubscriptionStatusFromRemoteJob extends RepairFailedDomainSubscriptionJob
{
    public function handle(
        FailedDomainSubscriptionRepairService $failedDomainSubscriptionRepairService,
        LoggerInterface $logger,
    ): void {
        $domainDeployment = $this->subscription->domainDeployment;

        $this->logDryRunOrExecuting(
            logger: $logger,
            dryRunMessage: 'Dry run: would sync local status from RTR.',
            executingMessage: 'Syncing local status from RTR.',
            domainDeployment: $domainDeployment,
        );

        if ($this->dryRun) {
            return;
        }

        $failedDomainSubscriptionRepairService->syncStatusFromRemote(
            subscription: $this->subscription,
            source: $this->triggeredBy,
        );
    }
}
