<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Actions;

use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Str;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DomainNameCoupleActionException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Results\DomainNameCoupleResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class DomainNameCoupleAction
{
    public function __construct(
        private readonly ProvisionGateway $provisionGateway,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DomainNameCoupleActionException
     * @throws ValidationException
     */
    public function execute(string $domain, Subscription $subscription): void
    {
        $provisionData = $this->provisionGateway->fetch(
            filters: new ProvisioningResultQueryFilters(tag: Uuid::fromString($subscription->uuid)),
            limit: 1
        )->first();

        if ($provisionData === null) {
            $this->logger->warning(
                'No provisioning data found during domain coupling for subscription {subscription.id} with UUID {subscription.uuid}.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME_COUPLING,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::SUBSCRIPTION_UUID => $subscription->uuid,
                ]
            );

            throw new DomainNameCoupleActionException(sprintf('No provisioning data found for subscription %s', $subscription->uuid));
        }

        $domainNameCoupleResult = $this->provisionGateway->request(
            new DomainNameCoupleRequest($domain, $provisionData->requestUuid, Str::uuid())
        );

        Assert::isInstanceOf($domainNameCoupleResult, DomainNameCoupleResult::class);

        if ($domainNameCoupleResult->failed) {
            $this->logger->warning(
                'Domain couple action domain {domain.name} failed.',
                [
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::INTERNAL,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::DOMAIN_NAME_COUPLING,
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::META => [
                        'couple_request_uuid' => $provisionData->requestUuid,
                    ],
                    LoggingContextKeys::EXCEPTION => $domainNameCoupleResult->exception,
                ]
            );

            if ($domainNameCoupleResult->exception instanceof ValidationException) {
                throw $domainNameCoupleResult->exception;
            }

            throw new DomainNameCoupleActionException(
                message: sprintf('Domain couple action failed for domain [%s]', $domain),
                previous: $domainNameCoupleResult->exception
            );
        }
    }
}
