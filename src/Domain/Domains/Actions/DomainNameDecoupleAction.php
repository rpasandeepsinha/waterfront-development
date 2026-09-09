<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Actions;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Str;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DomainNameDecoupleActionException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameDecoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Results\DomainNameDecoupleResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class DomainNameDecoupleAction
{
    public function __construct(
        private readonly ProvisionGateway $provisionGateway,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DomainNameDecoupleActionException
     */
    public function execute(string $domain, Subscription $subscription): void
    {
        $provisionData = $this->provisionGateway->fetch(
            filters: new ProvisioningResultQueryFilters(tag: Uuid::fromString($subscription->uuid)),
            limit: 1
        )->first();

        if ($provisionData === null) {
            $this->logger->warning(
                'No provisioning data found during domain decoupling for subscription {subscription.id} with UUID {subscription.uuid}.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME_COUPLING,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ]
            );

            throw new DomainNameDecoupleActionException(sprintf('No provisioning data found for subscription %s', $subscription->uuid));
        }

        $domainNameDecoupleResult = $this->provisionGateway->request(
            new DomainNameDecoupleRequest($domain, $provisionData->requestUuid, Str::uuid())
        );

        Assert::isInstanceOf($domainNameDecoupleResult, DomainNameDecoupleResult::class);

        if ($domainNameDecoupleResult->failed) {
            $this->logger->warning(
                'Domain decouple action domain {domain.name} failed.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME_COUPLING,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::META => [
                        'couple_request_uuid' => $provisionData->requestUuid,
                    ],
                    LoggingContextKeys::EXCEPTION => $domainNameDecoupleResult->exception,
                ]
            );

            throw new DomainNameDecoupleActionException(
                message: sprintf('Domain couple action failed for domain [%s]', $domain),
                previous: $domainNameDecoupleResult->exception
            );
        }
    }
}
