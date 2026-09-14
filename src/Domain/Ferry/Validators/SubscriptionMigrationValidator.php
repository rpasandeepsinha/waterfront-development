<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ferry\Validators;

use Waterfront\Domain\Domains\Enums\DomainStatus;
use Waterfront\Domain\Ferry\Exceptions\NotEligibleForMigrationException;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Domain\Subscriptions\Enums\AdministrativeStatus;
use Waterfront\Domain\Subscriptions\Enums\TechnicalStatus;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class SubscriptionMigrationValidator
{
    public function __construct(
        private readonly SitebuilderService $sitebuilderService,
    ) {
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForHostingMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if (
            $subscription->product->productGroup->slug !== ProductGroupType::HOSTING
            || $subscription->product->isRedirectProduct()
            || $subscription->product->isMailOnlyServer()
            || $subscription->product->isSitebuilderProduct()
        ) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        if ($subscription->hostingDeployment === null) {
            throw NotEligibleForMigrationException::missingHostingSubscription();
        }

        if ($subscription->hostingDeployment->provider?->slug !== ProviderSlug::PLACEHOLDER) {
            throw NotEligibleForMigrationException::incorrectHostingProvider($subscription->hostingDeployment->provider?->slug->value);
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForResellerHostingMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::RESELLER_HOSTING) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        if ($subscription->resellerHostingDeployment === null) {
            throw NotEligibleForMigrationException::missingResellerHostingSubscription();
        }

        if ($subscription->resellerHostingDeployment->provider->slug !== ProviderSlug::PLACEHOLDER) {
            throw NotEligibleForMigrationException::incorrectResellerHostingProvider($subscription->resellerHostingDeployment->provider->slug->value);
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForBackupMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::BACKUP) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        // Provisioning backups does not have a placeholder and no deployment on administrative migration.
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForMailOnlyMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::HOSTING) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        if ($subscription->hostingDeployment === null) {
            throw NotEligibleForMigrationException::missingHostingSubscription();
        }

        if (
            $subscription->hostingDeployment->mailProvider?->type !== ProviderType::MAILONLY
            || $subscription->hostingDeployment->mailProvider->slug !== ProviderSlug::PLACEHOLDER
        ) {
            throw NotEligibleForMigrationException::incorrectMailOnlyProvider($subscription->hostingDeployment->mailProvider?->slug->value);
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForSitebuilderMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::HOSTING) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        if (! $subscription->product->isSitebuilderProduct()) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        $deployment = $subscription->hostingDeployment;
        if ($deployment === null) {
            throw NotEligibleForMigrationException::missingHostingSubscription();
        }

        $mailProvider = $deployment->mailProvider;
        $sitebuilderProvider = $deployment->sitebuilderProvider;

        $hasMailPlaceholder =
            $mailProvider?->type === ProviderType::MAILONLY && $mailProvider->slug === ProviderSlug::PLACEHOLDER;

        $hasSitebuilderPlaceholder =
            $sitebuilderProvider?->type === ProviderType::SITEBUILDER
            && $sitebuilderProvider->slug === ProviderSlug::PLACEHOLDER;

        $subscription->loadMissing('customer');
        $isGatewaySitebuilder = $this->sitebuilderService->hasSitebuilderThroughGateway($subscription->customer->email);

        $invalid = $isGatewaySitebuilder
            ? ! $hasMailPlaceholder || $sitebuilderProvider !== null
            : ! $hasMailPlaceholder || ! $hasSitebuilderPlaceholder;

        if ($invalid) {
            throw NotEligibleForMigrationException::incorrectSitebuilderProvider(
                mailProviderSlug: $mailProvider?->slug->value,
                sitebuilderProviderSlug: $sitebuilderProvider?->slug->value,
            );
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForDomainMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                DomainStatus::ACTIVE->value,
                DomainStatus::FAILED->value,
                DomainStatus::MIGRATION_PENDING->value,
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::EXTENSION) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        if ($subscription->domainDeployment === null) {
            throw NotEligibleForMigrationException::missingDomainSubscription();
        }

        if ($subscription->domainDeployment->provider->slug !== ProviderSlug::PLACEHOLDER) {
            throw NotEligibleForMigrationException::incorrectDomainProvider($subscription->domainDeployment->provider->slug);
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForRedirectMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                DomainStatus::ACTIVE->value,
                DomainStatus::FAILED->value,
                DomainStatus::MIGRATION_PENDING->value,
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if (
            $subscription->product->productGroup->slug !== ProductGroupType::REDIRECT
            || ! $subscription->product->isRedirectProduct()
        ) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForDnsMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        switch ($subscription->product->productGroup->slug) {
            case ProductGroupType::EXTENSION:
                if (! in_array(
                    $subscription->technical_status,
                    [
                        DomainStatus::ACTIVE->value,
                        DomainStatus::FAILED->value,
                        DomainStatus::MIGRATION_PENDING->value,
                        TechnicalStatus::OK->value,
                        TechnicalStatus::FAILED->value,
                        TechnicalStatus::ERROR->value,
                        TechnicalStatus::PENDING->value,
                    ],
                    true,
                )) {
                    throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
                }

                if ($subscription->domainDeployment === null) {
                    throw NotEligibleForMigrationException::missingDomainSubscription();
                }

                break;

            case ProductGroupType::DNS:
                if ($subscription->technical_status !== TechnicalStatus::OK->value) {
                    throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
                }

                break;

            default:
                throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForNameserverMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                DomainStatus::ACTIVE->value,
                DomainStatus::FAILED->value,
                DomainStatus::MIGRATION_PENDING->value,
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::EXTENSION) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        if ($subscription->domainDeployment === null) {
            throw NotEligibleForMigrationException::missingDomainSubscription();
        }

        if ($subscription->domainDeployment->provider->slug === ProviderSlug::PLACEHOLDER) {
            throw NotEligibleForMigrationException::incorrectDomainProvider($subscription->domainDeployment->provider->slug);
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    public function validateEligibleForSslMigration(Subscription $subscription): void
    {
        $this->validateEligableBasedOnGeneralSubscriptionCriteria($subscription);

        if (! in_array(
            $subscription->technical_status,
            [
                DomainStatus::ACTIVE->value,
                DomainStatus::FAILED->value,
                DomainStatus::MIGRATION_PENDING->value,
                TechnicalStatus::OK->value,
                TechnicalStatus::FAILED->value,
                TechnicalStatus::ERROR->value,
                TechnicalStatus::PENDING->value,
            ],
            true,
        )) {
            throw NotEligibleForMigrationException::technicalStatusIncorrect($subscription->technical_status);
        }

        if ($subscription->product->productGroup->slug !== ProductGroupType::SSL) {
            throw NotEligibleForMigrationException::incorrectProduct($subscription->product->slug);
        }

        if ($subscription->sslDeployment === null) {
            throw NotEligibleForMigrationException::missingSslDeployment();
        }

        if ($subscription->sslDeployment->provider->slug !== ProviderSlug::PLACEHOLDER) {
            throw NotEligibleForMigrationException::incorrectSslProvider($subscription->sslDeployment->provider->slug);
        }
    }

    /**
     * @throws NotEligibleForMigrationException
     */
    private function validateEligableBasedOnGeneralSubscriptionCriteria(Subscription $subscription): void
    {
        if (! in_array(
            $subscription->administrative_status,
            [AdministrativeStatus::ACTIVE->value, AdministrativeStatus::CANCELED->value],
            true,
        )) {
            throw NotEligibleForMigrationException::administrativeStatusIncorrect($subscription->administrative_status);
        }
    }
}
