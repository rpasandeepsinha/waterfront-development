<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\SubscriptionProcessor\Builder;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Waterfront\Domain\DNS\Exceptions\DnsNamerverAlreadyAssignedException;
use Waterfront\Domain\DNS\Exceptions\FailedToFetchNameserversException;
use Waterfront\Domain\DNS\Factories\NameserverAssignerFactory;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Repository\DnsProductSpecRepository;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Domains\Services\DomainContactService;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\ExtensionMetaData;
use Waterfront\Domain\Orders\DTO\CartOrderLines\MetaData\MetaData;
use Waterfront\Domain\Orders\Models\Order;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Domain\Orders\Serializers\CartSerializerFactory;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Ssl\Factories\SslServiceFactory;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;

class EventSubscriptionDataBuilder
{
    public function __construct(
        private readonly DomainServiceFactory $domainServiceFactory,
        private readonly SslServiceFactory $sslServiceFactory,
        private readonly CartSerializerFactory $cartSerializerFactory,
        private readonly DomainDeploymentRepository $domainRepository,
        private readonly LoggerInterface $logger,
        private readonly DnsProductSpecRepository $dnsProductSpecRepository,
        private readonly DnsDeploymentRepository $dnsDeploymentRepository,
        private readonly NameserverAssignerFactory $nameserverAssignerFactory,
        private readonly DomainContactService $domainContactService,
    ) {
    }

    public function buildDomainDeployment(
        Subscription $subscription,
        bool $dnssecEnabled,
        bool $privateWhoisEnabled,
        ?string $transferSecret,
    ): DomainDeployment {
        $provider = $this->domainServiceFactory->resolveProviderByProduct($subscription->product);

        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = DomainDeployment::query()->create([
            'subscription_uuid' => $subscription->uuid,
            'provider_id' => $provider->id,
            'dnssec_enabled' => $dnssecEnabled,
            'private_whois_enabled' => $privateWhoisEnabled,
            'transfer_secret' => $transferSecret,
        ]);

        $childDnsSubscription = $this->domainRepository->getDnsChildSubscription($subscription);

        if ($childDnsSubscription === null) {
            $this->logger->warning(
                'Created a DomainDeployment({provisioning.id}) for subscription {subscription.id} where there is no DNS child subscription.',
                [
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::PROVISIONING_ID => $domainDeployment->id,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                    LoggingContextKeys::CUSTOMER_ID => $subscription->customer->id,
                ],
            );
        }

        return $domainDeployment;
    }

    public function buildSslDeployment(Subscription $subscription): void
    {
        $provider = $this->sslServiceFactory->resolveProviderByProduct($subscription->product);

        $deployment = new SslDeployment();
        $deployment->subscription_uuid = $subscription->uuid;
        $deployment->provider_id = $provider->id;
        $deployment->save();
    }

    /**
     * @throws FailedToFetchNameserversException
     * @throws DnsNamerverAlreadyAssignedException
     */
    public function buildDnsDeployment(Subscription $subscription): void
    {
        $dnsDeployment = $this->dnsDeploymentRepository->create(
            subscriptionUuid: $subscription->uuid,
            nameserverType: $this->dnsProductSpecRepository->isPremiumDns($subscription->product)
                ? NameserverType::VANITY
                : NameserverType::INTERNAL,
        );

        $this->nameserverAssignerFactory->createAssigner($dnsDeployment->nameserver_type)->assign($dnsDeployment);
    }

    public function buildOwnerAssociation(Subscription $subscription, int $contactId): void
    {
        $customer = $subscription->customer;
        $defaultOwner = $this->domainContactService->findOrCreateDefaultOwner($customer);
        $owner = $customer->domainContacts()->where('id', $contactId)->first() ?? $defaultOwner;

        if ($subscription->domainDeployment === null) {
            throw new RuntimeException('domain subscription can not be null');
        }

        $subscription->domainDeployment->contactOwner()->associate($owner);
        $subscription->domainDeployment->save();
    }

    public function buildPrivateWhoisStatus(Subscription $subscription, ?ExtensionMetaData $metaData): bool
    {
        $domain = $subscription->domain;
        $order = $subscription->orderLineItem?->order;

        if (! $order instanceof Order) {
            return false;
        }

        if (! $subscription
            ->product
            ->productSpecs()
            ->where('name', 'domain.allow_whois_private')
            ->where('value', 'yes')
            ->exists()) {
            return false;
        }

        return (
            $metaData->privateWhois ?? $order
                ->lineItems
                ->filter(
                    fn (OrderLineItem $item): bool => (
                        $item->domain === $domain
                        && $item->product?->slug === 'extension_privacy_protection'
                    ),
                )
                ->isNotEmpty()
        );
    }

    public function buildDnssecEnabled(Subscription $subscription): bool
    {
        return $subscription
            ->product
            ->productSpecs()
            ->where('name', ProductSpecName::DOMAIN_DNSSEC_ENABLED)
            ->where('value', 'yes')
            ->exists();
    }

    public function buildExtensionMetaData(?string $metaData): ?ExtensionMetaData
    {
        if ($metaData === null) {
            return null;
        }

        $metaData = $this->cartSerializerFactory->get()->deserialize($metaData, MetaData::class, 'json');

        assert($metaData instanceof ExtensionMetaData);

        return $metaData;
    }
}
