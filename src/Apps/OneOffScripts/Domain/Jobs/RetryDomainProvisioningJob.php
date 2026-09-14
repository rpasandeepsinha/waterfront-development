<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Domain\Jobs;

use Illuminate\Contracts\Bus\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\Domains\Jobs\RegisterDomainNameJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder\EventSubscriptionDataBuilder;
use Waterfront\Support\Enums\LoggingContextKeys;

class RetryDomainProvisioningJob extends RepairFailedDomainSubscriptionJob
{
    public function handle(
        Dispatcher $dispatcher,
        LoggerInterface $logger,
        EventSubscriptionDataBuilder $eventSubscriptionDataBuilder,
    ): void {
        $domainDeployment = $this->subscription->domainDeployment;

        $this->logDryRunOrExecuting(
            logger: $logger,
            dryRunMessage: 'Dry run: would retry provisioning because domain does not exist at RTR.',
            executingMessage: 'Retrying provisioning because domain does not exist at RTR.',
            domainDeployment: $domainDeployment,
        );

        if ($this->dryRun) {
            return;
        }

        if (! $domainDeployment instanceof DomainDeployment) {
            $extensionMetaData = $eventSubscriptionDataBuilder->buildExtensionMetaData(
                $this->subscription->orderLineItem?->meta_data,
            );

            $domainDeployment = $eventSubscriptionDataBuilder->buildDomainDeployment(
                subscription: $this->subscription,
                dnssecEnabled: $eventSubscriptionDataBuilder->buildDnssecEnabled($this->subscription),
                privateWhoisEnabled: $eventSubscriptionDataBuilder->buildPrivateWhoisStatus(
                    $this->subscription,
                    $extensionMetaData,
                ),
                transferSecret: null,
            );

            $logger->info(
                'Created missing domain deployment before provisioning retry.',
                $this->buildLogContext($domainDeployment)
                + [
                    LoggingContextKeys::META => [
                        'dry_run' => $this->dryRun,
                        'reason' => 'missing_domain_deployment_recreated',
                    ],
                ],
            );
        }

        $dispatcher->dispatch(new RegisterDomainNameJob($domainDeployment));
    }
}
