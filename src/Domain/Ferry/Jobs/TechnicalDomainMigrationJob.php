<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Exceptions\DomainContactException;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Exceptions\EnableAutorenewalFailedException;
use Waterfront\Domain\Domains\Models\DomainContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Actions\Domains\LinkTemplateToDomainDeploymentAction;
use Waterfront\Domain\Ferry\Dto\Domains\DomainMigrationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationSource;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\DomainBusinessUnitNotFoundException;
use Waterfront\Domain\Ferry\Exceptions\DomainContactHandleException;
use Waterfront\Domain\Ferry\Exceptions\DomainDeploymentNotFoundException;
use Waterfront\Domain\Ferry\Exceptions\NoCredentialsForDomainBusinessUnitException;
use Waterfront\Domain\Ferry\Mappers\TechnicalDomainMigrationMapper;
use Waterfront\Domain\Ferry\Services\DomainAndSslMigrationService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\RtrClient\Exceptions\ContactDoesNotExistException;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class TechnicalDomainMigrationJob extends MigrationJob implements ShouldQueue
{
    private TechnicalDomainMigrationMapper $domainMapper;

    private DomainAndSslMigrationService $domainAndSslMigrationService;

    private DomainService $domainService;

    private LinkTemplateToDomainDeploymentAction $linkTemplateToDomainDeploymentAction;

    public function __construct(
        public Subscription $subscription,
        protected ?string $failedTechnicalStatus,
        protected ?DomainMigrationPayload $payload,
        protected MigrationSource $source = MigrationSource::AZURE_DATA_FACTORY,
    ) {
        parent::__construct($this->subscription, $this->failedTechnicalStatus, $source);
    }

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::DOMAIN_MIGRATION;
    }

    /**
     * @throws DomainDeploymentNotFoundException
     * @throws DomainContactException
     * @throws DomainContactHandleException
     * @throws ContactDoesNotExistException
     * @throws DomainBusinessUnitNotFoundException
     * @throws EnableAutorenewalFailedException
     * @throws NoCredentialsForDomainBusinessUnitException
     */
    protected function runMigration(): void
    {
        $domain = $this->subscription->domain;

        Assert::string(
            $domain,
            sprintf(
                'For domain migrations the domain value must be a string. Subscription ID:{%d}',
                $this->subscription->id,
            ),
        );

        if ($this->subscription->domainDeployment === null) {
            throw new DomainDeploymentNotFoundException(sprintf('Domain subscription not found %s', $domain));
        }

        $driver = $this->payload->driver ?? ProviderSlug::REALTIME_REGISTER;

        if ($this->payload?->referenceDomainProviderBusinessUnitSlug !== null) {
            $this->domainAndSslMigrationService->attachBusinessUnitToDomainDeployment(
                deployment: $this->subscription->domainDeployment,
                businessUnitSlug: $this->payload->referenceDomainProviderBusinessUnitSlug,
                providerSlug: $driver,
            );
        }

        $mappedDomain = $this->domainMapper->mapSubscriptionWithRemoteResult(
            $this->subscription,
            $this->migratedCustomer,
            $driver,
        );

        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = $mappedDomain->subscription->domainDeployment;

        /** @var Provider $domainProvider */
        $domainProvider = Provider::query()->where('slug', $driver)->where('type', ProviderType::DOMAIN)->firstOrFail();

        $defaultOwnerDomainContact = $this->domainAndSslMigrationService->createMigratedDomainContact(
            migratedCustomer: $this->migratedCustomer,
            customer: $this->subscription->customer,
            subscription: $this->subscription,
            domainProvider: $domainProvider,
            migrationDomain: $mappedDomain,
        );

        $this->domainAndSslMigrationService->attachOwnerToDeployment($defaultOwnerDomainContact, $domainDeployment);

        $this->domainAndSslMigrationService->attachProviderToDeployment(
            $domainProvider,
            $domainDeployment,
        );

        if ($this->payload !== null) {
            $this->linkTemplateToDomainDeploymentAction->execute($domainDeployment, $this->payload);
        }

        if ($mappedDomain->domainDetails->autoRenew === false) {
            $this->domainService->enableAutoRenewal($domain, $driver);
        }

        try {
            $this->domainService->enablePrivateWhois($domainDeployment);
        } catch (DomainModificationFailedException) {
            $this->logger->debug(
                'Unable to enable privacy protect for given domain in migration',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $this->migratedCustomer->reference_customer_number,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $driver->value,
                ],
            );
        }
    }

    protected function rollback(Throwable $throwable): void
    {
        $domainDeployment = $this->subscription->domainDeployment()->first();

        if (! $domainDeployment instanceof DomainDeployment) {
            $this->logger->debug(
                'No domain deployment found',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $this->subscription->domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $this->migratedCustomer->reference_customer_number,
                ],
            );

            return;
        }

        /** @var Provider $placeHolderProvider */
        $placeHolderProvider = Provider::where('type', ProviderType::DOMAIN)
            ->where('slug', ProviderSlug::PLACEHOLDER)
            ->first();

        $domainDeployment->provider_id = $placeHolderProvider->id;
        $domainDeployment->domain_business_unit_id = null;
        $domainContact = $domainDeployment->contactOwner;
        /** @var DomainContact|null $domainContact */
        $domainDeployment->contact_owner_id = null;
        $domainDeployment->save();

        if ($domainContact instanceof DomainContact && $domainContact->contactOwnerDomainSubscriptions->isEmpty()) {
            $domainContact->delete();
        }
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return DomainStatus::ACTIVE->value;
    }

    protected function registerServices(): void
    {
        $this->domainMapper = $this->resolve(TechnicalDomainMigrationMapper::class);
        $this->domainAndSslMigrationService = $this->resolve(DomainAndSslMigrationService::class);
        $this->domainService = $this->resolve(DomainService::class);
        $this->linkTemplateToDomainDeploymentAction = $this->resolve(LinkTemplateToDomainDeploymentAction::class);
    }
}
