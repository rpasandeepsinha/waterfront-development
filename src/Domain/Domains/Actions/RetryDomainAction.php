<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\Events\CreateDns;
use Waterfront\Domain\Domains\Events\CreateDomain;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class RetryDomainAction
{
    public function __construct(
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
        private readonly LoggerInterface $logger,
        private readonly Dispatcher $eventDispatcher,
    ) {
    }

    public function execute(
        DomainDeployment $domainDeployment,
        bool $enableDnssec,
        bool $privateWhois,
        ?string $transferSecret
    ): void {
        $subscription = $domainDeployment->subscription;

        $domain = $subscription->domain;
        if ($domain === null || $domain === '') {
            $this->logger->notice(
                'Retry Domain failed because domain name is missing for subscription [{subscription.uuid}]',
                [
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ]
            );

            return;
        }

        $domainDeployment->update([
            'dnssec_enabled' => $enableDnssec,
            'transfer_secret' => $transferSecret,
            'private_whois_enabled' => $privateWhois,
        ]);

        $dnsSubscription = $this->domainDeploymentRepository->getDnsChildSubscription($subscription);
        if ($dnsSubscription === null) {
            $this->logger->notice(
                'Retry Domain failed because DNS subscription could not be found for domain [{domain}]',
                [
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ]
            );

            return;
        }

        $this->eventDispatcher->dispatch(
            new CreateDns(
                $dnsSubscription->uuid,
                $domain,
            )
        );

        $this->eventDispatcher->dispatch(
            new CreateDomain(
                domain: $domain,
                subscription: $subscription,
                domainDeployment: $domainDeployment,
            )
        );
    }
}
