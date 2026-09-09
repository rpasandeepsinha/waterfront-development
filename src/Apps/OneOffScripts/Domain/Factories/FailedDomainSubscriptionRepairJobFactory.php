<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Factories;

use Waterfront\Apps\OneOffScripts\Domain\DTO\FailedDomainSubscriptionRepair;
use Waterfront\Apps\OneOffScripts\Domain\Enum\FailedDomainSubscriptionRepairPath;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RepairFailedDomainSubscriptionJob;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RepairMissingRemoteNameserversJob;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RestoreFromProcesses;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\RetryDomainProvisioningJob;
use Waterfront\Apps\OneOffScripts\Domain\Jobs\SyncDomainSubscriptionStatusFromRemoteJob;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class FailedDomainSubscriptionRepairJobFactory
{
    public function make(
        FailedDomainSubscriptionRepair $repairPlan,
        Subscription $subscription,
        bool $dryRun,
        string $triggeredBy,
    ): ?RepairFailedDomainSubscriptionJob {
        return match ($repairPlan->path) {
            FailedDomainSubscriptionRepairPath::REPAIR_NAMESERVERS => new RepairMissingRemoteNameserversJob(
                subscription: $subscription,
                dryRun: $dryRun,
                triggeredBy: $triggeredBy,
            ),
            FailedDomainSubscriptionRepairPath::RESTORE_FROM_PROCESS => new RestoreFromProcesses(
                processCollection: $repairPlan->processCollection,
                subscription: $subscription,
                dryRun: $dryRun,
                triggeredBy: $triggeredBy,
            ),
            FailedDomainSubscriptionRepairPath::RETRY_PROVISIONING => new RetryDomainProvisioningJob(
                subscription: $subscription,
                dryRun: $dryRun,
                triggeredBy: $triggeredBy,
            ),
            FailedDomainSubscriptionRepairPath::SYNC_STATUS_ONLY => new SyncDomainSubscriptionStatusFromRemoteJob(
                subscription: $subscription,
                dryRun: $dryRun,
                triggeredBy: $triggeredBy,
            ),
            FailedDomainSubscriptionRepairPath::SKIP => null,
        };
    }
}
