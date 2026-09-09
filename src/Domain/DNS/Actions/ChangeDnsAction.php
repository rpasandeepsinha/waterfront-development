<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Actions;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Psr\Log\LoggerInterface;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Exceptions\DnsChangeException;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Domain\DNS\Services\DnsVanityNameserverAssigner;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\Enums\ProductChangeType;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\GandiClient\GandiClient;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class ChangeDnsAction
{
    public function __construct(
        private readonly DnsService $dnsService,
        private readonly LoggerInterface $logger,
        private readonly GandiClient $gandiClient,
        private readonly DnsVanityNameserverAssigner $dnsVanityNameserverAssigner,
        private readonly DnsNameserverAssigner $dnsNameserverAssigner
    ) {
    }

    /**
     * @throws DnsChangeException
     * @throws GuzzleException
     * @throws JsonException
     * @throws PdnsResponseException
     */
    public function execute(
        Subscription $subscription,
        ProductChangeType $changeType
    ): void {
        $this->validateSubscriptionGroup($subscription);

        $subscription->technical_status = TechnicalStatus::PENDING->value;
        $subscription->save();

        $changeType === ProductChangeType::UPGRADE
            ? $this->upgradeToPremiumDns($subscription)
            : $this->downgradeFromPremiumDns($subscription);
    }

    /**
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws JsonException
     */
    private function upgradeToPremiumDns(Subscription $subscription): void
    {
        Assert::stringNotEmpty($subscription->domain);

        $this->logger->info(
            'Upgrading subscription with domain {domain.name} to Premium DNS',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]
        );

        $dnsDeployment = $subscription->dnsDeployment;

        if ($dnsDeployment === null) {
            $this->logger->error(
                'DNS Deployment is missing for subscription with domain {domain.name} during upgrade',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ]
            );
            throw new DnsDeploymentNotFoundException($subscription->domain);
        }

        $this->logger->debug(
            'Enable vanity nameserver on DnsDeployment for subscription with domain {domain.name} during upgrade',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]
        );

        $dnsDeployment->nameserver_type = NameserverType::VANITY;
        $dnsDeployment->save();

        if (! $dnsDeployment->vanityNameservers()->exists()) {
            $this->dnsVanityNameserverAssigner->assign($dnsDeployment);
        }

        $this->dnsService->enablePremiumDns($subscription->domain);

        $this->logger->debug(
            'Remove regular nameservers for subscription with domain {domain.name} during upgrade',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]
        );

        $this->dnsNameserverAssigner->clear($dnsDeployment);
    }

    /**
     * @throws DomainModificationFailedException
     * @throws JsonException
     * @throws DnsZoneNotFoundException
     */
    private function downgradeFromPremiumDns(Subscription $subscription): void
    {
        Assert::stringNotEmpty($subscription->domain);

        $this->logger->info(
            'Downgrading subscription with domain {domain.name} from Premium DNS',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]
        );

        $dnsDeployment = $subscription->dnsDeployment;

        if ($dnsDeployment === null) {
            $this->logger->error(
                'DNS Deployment is missing for subscription with domain {domain.name} during downgrade',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                ]
            );
            throw new DnsDeploymentNotFoundException($subscription->domain);
        }

        $this->dnsNameserverAssigner->assign($dnsDeployment);

        $this->dnsService->disablePremiumDns(domain: $subscription->domain, shouldUpdateNameservers: true);
        $this->gandiClient->deleteDomain($subscription->domain);

        $this->logger->debug(
            'Remove vanity nameservers for subscription with domain {domain.name} during downgrade',
            [
                LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
            ]
        );

        $this->dnsVanityNameserverAssigner->clear($dnsDeployment);
    }

    /**
     * * @throws DnsChangeException
     */
    private function validateSubscriptionGroup(Subscription $subscription): void
    {
        if ($subscription->product->productGroup->slug !== ProductGroupType::DNS) {
            $this->logger->error(
                'Invalid subscription with domain {domain.name} for DNS change. Expected subscription with product group {expected.product_group} but got {actual.product_group}',
                [
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::META => [
                        'expected.product_group' => ProductGroupType::DNS->value,
                        'actual.product_group' => $subscription->product->productGroup->slug->value,
                    ],
                ]
            );

            throw new DnsChangeException(
                sprintf(
                    'Invalid subscription %s for DNS change. Expected subscription with product group %s but got %s',
                    $subscription->domain,
                    ProductGroupType::DNS->value,
                    $subscription->product->productGroup->slug->value
                )
            );
        }
    }
}
