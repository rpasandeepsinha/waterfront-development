<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use UnexpectedValueException;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\UnknownProductGroupException;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Infra\RtrClient\Exceptions\DnsSecNotSupportedForTldException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class EnableDnsSecMigrationJob extends MigrationJob implements ShouldQueue
{
    private DomainService $domainService;

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::ENABLE_DNSSEC;
    }

    /**
     * @throws DnsSecNotSupportedForTldException
     * @throws Exception
     */
    protected function runMigration(): void
    {
        $domain = $this->subscription->domain;
        Assert::notNull(
            $domain,
            sprintf(
                'Provided subscription with ID: {%d} has no domain',
                $this->subscription->id
            )
        );

        /** @var ProviderSlug $providerSlug */
        $providerSlug = $this->subscription->domainDeployment()->firstOrFail()->provider->slug;

        if (! $this->domainService->isDnssecSupported($domain, $providerSlug)) {
            throw new DnsSecNotSupportedForTldException(sprintf(
                'DnsSec is not support for the tld with the domain: {%s}',
                $domain
            ));
        }

        $this->logger->debug(
            'Enabling DNSSEC for migration domain',
            [
                LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                LoggingContextKeys::DOMAIN_NAME => $domain,
                LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID => $this->migratedCustomer->reference_customer_number,
            ]
        );

        if (! $this->domainService->enableDnssec($domain, $providerSlug)) {
            throw new UnexpectedValueException(sprintf(
                'DnsSec could not be enabled for domain: {%s} needs deeper research',
                $domain
            ));
        }
    }

    /**
     * @throws UnknownProductGroupException
     */
    protected function getSuccessfulTechnicalStatus(): string
    {
        return match (ProductGroupType::from($this->subscription->product->productGroup->slug->value)) {
            ProductGroupType::EXTENSION => DomainStatus::ACTIVE->value,
            ProductGroupType::DNS => TechnicalStatus::OK->value,
            default => throw new UnknownProductGroupException(
                'Unknown product group type: ' . $this->subscription->product->productGroup->slug->value
            )
        };
    }

    protected function rollback(Throwable $throwable): void
    {
        //...
    }

    protected function registerServices(): void
    {
        $this->domainService = $this->resolve(DomainService::class);
    }
}
