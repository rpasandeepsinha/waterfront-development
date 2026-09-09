<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\OneOffScripts\Domain\Factories\FailedDomainSubscriptionRepairJobFactory;
use Waterfront\Apps\OneOffScripts\Domain\Services\FailedDomainSubscriptionRepairService;
use Waterfront\Support\Enums\LoggingContextKeys;

class DiagnoseFailedDomainSubscriptionJob extends RepairFailedDomainSubscriptionJob
{
    public function handle(
        Dispatcher $dispatcher,
        FailedDomainSubscriptionRepairJobFactory $repairJobFactory,
        FailedDomainSubscriptionRepairService $failedDomainSubscriptionRepairService,
        LoggerInterface $logger,
    ): void {
        $domainDeployment = $this->subscription->domainDeployment;
        $logContext = $this->buildLogContext($domainDeployment);

        $repairPlan = $failedDomainSubscriptionRepairService->determineRepair(
            subscription: $this->subscription,
            source: $this->triggeredBy
        );

        $logger->info(
            'Diagnosed failed domain subscription repair path.',
            array_merge($logContext, [
                LoggingContextKeys::META => [
                    'dry_run' => $this->dryRun,
                    'repair_path' => $repairPlan->path->value,
                    'reason' => $repairPlan->reason,
                    'processes' => $repairPlan->processCollection?->toArray(),
                ],
            ]),
        );

        $job = $repairJobFactory->make(
            repairPlan: $repairPlan,
            subscription: $this->subscription,
            dryRun: $this->dryRun,
            triggeredBy: $this->triggeredBy,
        );

        if ($job instanceof RepairFailedDomainSubscriptionJob) {
            $dispatcher->dispatch($job);
        }
    }
}
