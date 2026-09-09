<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Mappers;

use Psr\Log\LoggerInterface;
use RealtimeRegister\Exceptions\ForbiddenException;
use Waterfront\Domain\Customers\Models\MigratedCustomer;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\FetchDomainException;
use Waterfront\Domain\Ferry\Dto\Domains\TechnicalMigrationDomain;
use Waterfront\Domain\Ferry\Exceptions\RemoteDomainForbiddenException;
use Waterfront\Domain\Ferry\Exceptions\RemoteDomainNotFoundException;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class TechnicalDomainMigrationMapper
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function mapSubscriptionWithRemoteResult(
        Subscription $subscription,
        MigratedCustomer $migratedCustomer,
        ProviderSlug $driver
    ): TechnicalMigrationDomain {
        $domain = $subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        try {
            $remoteResult = $this->domainService->fetchDomain($domain, $driver);
        } catch (FetchDomainException $exception) {
            $this->logger->warning(
                'Unable to fetch domain from backend',
                [
                    LoggingContextKeys::SUBSCRIPTION_ID => $subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $subscription->domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $migratedCustomer->reference_customer_number,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'driver' => $driver,
                        'business_unit_slug' => $subscription->domainDeployment?->businessUnit?->slug,
                    ],
                ]
            );

            $previousException = $exception->getPrevious();

            if ($previousException instanceof ForbiddenException) {
                throw new RemoteDomainForbiddenException($subscription, $driver, $exception);
            }
            throw new RemoteDomainNotFoundException($subscription, $driver, $exception);
        }

        return new TechnicalMigrationDomain($subscription, $remoteResult);
    }
}
