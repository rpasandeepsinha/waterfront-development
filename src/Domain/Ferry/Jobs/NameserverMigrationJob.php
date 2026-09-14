<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Jobs;

use Throwable;
use Waterfront\Domain\DNS\Actions\AssignNameserversToDomainAction;
use Waterfront\Domain\DNS\Models\DnsDeployment;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Ferry\Enums\MigrationStep;
use Waterfront\Domain\Ferry\Exceptions\DomainWithoutDnsDeploymentException;
use Waterfront\Domain\Ferry\Services\DnsMigrationService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Support\Enums\LoggingContextKeys;

class NameserverMigrationJob extends MigrationJob
{
    public int $tries = 8;

    private DnsMigrationService $dnsMigrationService;

    private AssignNameserversToDomainAction $assignNameserversToDomainAction;

    private DnsDeploymentRepository $dnsDeploymentRepository;

    public function getMigrationStep(): MigrationStep
    {
        return MigrationStep::NAMESERVER;
    }

    protected function runMigration(): void
    {
        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = $this->subscription->domainDeployment()->firstOrFail();

        $domainDeploymentProviderSlug = $domainDeployment->provider->slug;

        $domain = $this->subscription->domain;
        assert(is_string($domain));

        $dnsDeployment = $this->dnsDeploymentRepository->getDnsDeploymentFromDomainDeployment($domainDeployment);

        if ($dnsDeployment === null) {
            throw new DomainWithoutDnsDeploymentException($domain);
        }

        if ($this->dnsMigrationService->hasModernInternalNameservers(
            subscriptionId: $this->subscription->id,
            domain: $domain,
            referenceCustomerNumber: $this->migratedCustomer->reference_customer_number,
            driver: $domainDeploymentProviderSlug,
        )) {
            $this->logger->debug(
                'Migration domain already has modern internal nameservers',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $this->migratedCustomer->reference_customer_number,
                ],
            );

            return;
        }

        if ($this->dnsMigrationService->hasLegacyInternalNameservers(
            subscriptionId: $this->subscription->id,
            domain: $domain,
            referenceCustomerNumber: $this->migratedCustomer->reference_customer_number,
            driver: $domainDeploymentProviderSlug,
        )) {
            $this->logger->debug(
                'Migration domain has legacy internal nameservers, assigning internal nameservers',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $this->migratedCustomer->reference_customer_number,
                ],
            );

            $this->assignInternalNameservers($dnsDeployment, $domain, $domainDeploymentProviderSlug);

            return;
        }

        if ($this->dnsMigrationService->hasLegacyWhitelabelNameservers(
            subscriptionId: $this->subscription->id,
            domain: $domain,
            referenceCustomerNumber: $this->migratedCustomer->reference_customer_number,
            driver: $domainDeploymentProviderSlug,
        )) {
            $this->logger->debug(
                'Migration domain has legacy whitelabel internal nameservers, assigning internal nameservers',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $this->migratedCustomer->reference_customer_number,
                ],
            );

            $this->assignInternalNameservers($dnsDeployment, $domain, $domainDeploymentProviderSlug);

            return;
        }

        $this->dnsDeploymentRepository->setNameserverType($dnsDeployment, NameserverType::EXTERNAL);
        $this->assignNameserversToDomainAction->assign(
            domainDeployment: $domainDeployment,
            shouldProvision: false,
        );
    }

    protected function rollback(Throwable $throwable): void
    {
        //...
    }

    protected function getSuccessfulTechnicalStatus(): string
    {
        return DomainStatus::ACTIVE->value;
    }

    protected function registerServices(): void
    {
        $this->dnsMigrationService = $this->resolve(DnsMigrationService::class);
        $this->assignNameserversToDomainAction = $this->resolve(AssignNameserversToDomainAction::class);
        $this->dnsDeploymentRepository = $this->resolve(DnsDeploymentRepository::class);
    }

    private function assignInternalNameservers(
        DnsDeployment $dnsDeployment,
        string $domain,
        ProviderSlug $domainDeploymentProviderSlug,
    ): void {
        $this->dnsDeploymentRepository->setNameserverType($dnsDeployment, NameserverType::INTERNAL);

        try {
            // The assignToDomain flow can be repeated safely.
            $this->assignNameserversToDomainAction->assignToDomain($domain);
        } catch (DomainModificationFailedException $exception) {
            $this->logger->debug(
                'Nameserver change failed, checking if we need to reset DNSSEC...',
                [
                    LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                    LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                    LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                    LoggingContextKeys::DOMAIN_NAME => $domain,
                    LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                        $this->migratedCustomer->reference_customer_number,
                    LoggingContextKeys::PROVISIONING_PROVIDER => $domainDeploymentProviderSlug->value,
                    LoggingContextKeys::EXCEPTION => $exception,
                ],
            );

            // Only reset DNSSEC on the first and second to last attempt
            if ($this->attempts() === 1 || $this->attempts() === ($this->tries - 1)) {
                // ticket: https://yh-jira.atlassian.net/browse/SWD-9386
                $this->logger->debug(
                    'Resetting DNSSEC',
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                        LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                        LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                            $this->migratedCustomer->reference_customer_number,
                        LoggingContextKeys::PROVISIONING_PROVIDER => $domainDeploymentProviderSlug->value,
                        LoggingContextKeys::EXCEPTION => $exception,
                    ],
                );

                $this->dnsMigrationService->resetDnssec($domain, $domainDeploymentProviderSlug);
            }

            try {
                $this->assignNameserversToDomainAction->assignToDomain($domain);
            } catch (DomainModificationFailedException $exception) {
                $delay = 10800; // three hours

                $this->logger->debug(
                    "Nameserver change failed, retrying after $delay seconds",
                    [
                        LoggingContextKeys::QUEUE_JOB_ID => $this->getJobId(),
                        LoggingContextKeys::QUEUE_ATTEMPT => $this->attempts(),
                        LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                        LoggingContextKeys::DOMAIN_NAME => $domain,
                        LoggingContextKeys::MIGRATION_REFERENCE_CUSTOMER_ID =>
                            $this->migratedCustomer->reference_customer_number,
                        LoggingContextKeys::EXCEPTION => $exception,
                    ],
                );

                $this->release($delay);
            }
        }
    }
}
